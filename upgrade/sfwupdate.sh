#!/usr/bin/expect -f
# Enable debugging by setting exp_internal to 1
# Uncomment for debugging
#exp_internal 1
# Uncomment for debugging
#log_file ufw_debug.log

# Terminal environment settings
#set env(TERM) dumb
# Default timeout for all commands
set timeout 5

# Retrieve CSV file path, firmware name, and result file path from arguments
set filename [lindex $argv 0]
set fwname [lindex $argv 1]
set result_file_path [lindex $argv 2] ;# Result file path

# =========================================================
# Insert UTF-8 BOM (Unicode \uFEFF)
# 1. Open result file in write mode ('w')
set result_fp [open $result_file_path w]

# 2. Explicitly set channel encoding to UTF-8 (critical)
fconfigure $result_fp -encoding utf-8

# 3. Write BOM character \uFEFF
#    Tcl outputs this as 0xEF 0xBB 0xBF (3 bytes) in UTF-8, recognized by Excel
puts -nonewline $result_fp "\uFEFF"

# 4. Write CSV header
puts $result_fp "IP,Result_Message"

# 5. Close file and reopen in append mode ('a') with UTF-8 encoding
close $result_fp
set result_fp [open $result_file_path a]
fconfigure $result_fp -encoding utf-8
# =========================================================

# Function to log results to CSV
proc log_result {ip status_msg} {
    global result_fp
    puts $result_fp "$ip,$status_msg" ;# Maintain comma separator
}

# Define firmware upgrade logic in a separate procedure
proc upgrade_ap {ip model version fw_server_ip fwname} {
    puts "Upgrading AP $ip ($model) from version $version"
    # Configure firmware settings
    send "fw set proto HTTP\r"
    expect "OK"
    send "fw set port 80\r"
    expect "OK"
    send "fw set host $fw_server_ip\r"
    expect "OK"
    send "fw set control upgrade/$fwname\r"
    expect "OK"
    
    send "fw update\r"
    
    # First timeout setting (5 seconds)
    set timeout 5
    
    expect {
        # Check for immediate errors or failures within 5 seconds
        -re {.*Control File Download Error.*|.*cannot connect to remote host.*rkscli} {
            set status_msg "Please ensure port 80 on the server is open."
            puts "$ip $status_msg"
            log_result $ip $status_msg
            set timeout 5
            return 1
        }
        -re "needs a reboot|Unable to get active image header" {
            set status_msg "Upgrade failed, resetting and rebooting AP. Please retry the upgrade."
            puts "$ip $status_msg"
            send "set factory\r"; expect "OK"
            sleep 2
            send "reboot\r"; expect "OK"
            puts "Rebooting $ip."
            log_result $ip $status_msg
            set timeout 5
            return 1			
        }
        # No pattern within 5 seconds = assume download is progressing normally
        timeout {
            # Proceed to next block
        }
    }

    # Second timeout setting (180 seconds)
    set timeout 180
    
    # Log final result after extracting AP model and version
    set final_result 1 ;# Default to failure

    expect {
        # Same version detected
        -re {.*No update.*OK} {
            set status_msg "Same version detected. Moving to next device."
            puts "$ip $status_msg"
            log_result $ip $status_msg
            return 1
        }
        # Upgrade completed (success)
        -re {fw\([0-9]+\) : Completed} {
            set status_msg "Upgrade completed, resetting and rebooting AP."
            puts "$ip $status_msg"
            send "\r"
            sleep 1
            send "set factory\r"; expect "OK"
            sleep 2
            send "reboot\r"; expect "OK"
            puts "Rebooting $ip."
            log_result $ip $status_msg
            set timeout 5
            return 0
        }
        # Timeout after 180 seconds (slow or failed)
        timeout {
            set status_msg "Firmware update did not complete within 180 seconds. Check network and file availability."
            puts "$ip $status_msg"
            log_result $ip $status_msg
            set timeout 5  ;# Restore original timeout
            return 1
        }
    }
}

# Open CSV file
set fp [open $filename r]
set index 0

while {[gets $fp line] != -1} {
    if {$index == 0} {
        # Skip header line as BOM and header are already written
        incr index
        continue
    }
    incr index

    # Split fields by comma
    set fields [split $line ","]
    
    if {[llength $fields] != 4} {
        puts "Invalid format: $line"
        continue
    }
    # Assign variables
    set ip [lindex $fields 0]
    set user [lindex $fields 1]
    set pass [lindex $fields 2]
    set fw_server_ip [lindex $fields 3]
    
    # Print processing message
    puts "Processing: Upgrading firmware of $ip to standalone firmware..."

    # SSH connection
    spawn ssh -legacy -t -o StrictHostKeyChecking=no -o HostKeyAlgorithms=+ssh-rsa -o ForwardX11=no $user@$ip
    sleep 2
    
    # Login attempt
    expect {
        "ssh: connect to host" {
            set status_msg "Unable to connect."
            puts "($ip $status_msg Moving to next device.)"
            log_result $ip $status_msg
            continue
        }
        timeout {
            set status_msg "Connection timeout."
            puts "($ip $status_msg Moving to next device.)"
            log_result $ip $status_msg
            continue
        }
        -re "Please login" {
            send "$user\r"
        }
    }
        
    # Enter password
    expect -re "password"
    send "$pass\r"
    # Handle login scenarios
    expect {
        # 0) Unleashed device
        -re "ruckus>" {
            send "enable\r"
            expect {
                # 0-0-1) Requires force after enable
                -re "force" {
                    send "enable force\r"
                    expect -re "ruckus#"
                    send "ap-mode\r"
                    expect -re "ap-mode"
                    send "get version\r"
                    expect {
                        -re {Ruckus\s(\S+)\s.*\nVersion:\s(\S+)} {
                            set model $expect_out(1,string)
                            set version $expect_out(2,string)
                            puts "Model: $model, Version: $version"
                            upgrade_ap $ip $model $version $fw_server_ip $fwname
                        }
                    }
                }
                # 0-0-2) Directly enters enable mode
                -re "ruckus#" {  ;# Directly to ruckus# after enable
                    send "ap-mode\r"
                    expect -re "ap-mode" {
                        send "get version\r"
                        expect {
                            -re {Ruckus\s(\S+)\s.*\nVersion:\s(\S+)} {
                                set model $expect_out(1,string)
                                set version $expect_out(2,string)
                                puts "Model: $model, Version: $version"
                                upgrade_ap $ip $model $version $fw_server_ip $fwname
                            }
                        }
                    }
                }			
            }
        }
        # 0-1) Unleashed firmware in reset state
        -re "yes/no" {  
            send "no\r"
            expect -re "ruckus>"
            send "enable\r"
            expect {
                # 0-1-1) Requires force after enable
                -re "force" {
                    send "enable force\r"
                    expect -re "ruckus#"
                    send "ap-mode\r"
                    expect -re "ap-mode"
                    send "get version\r"
                    expect {
                        -re {Ruckus\s(\S+)\s.*\nVersion:\s(\S+)} {
                            set model $expect_out(1,string)
                            set version $expect_out(2,string)
                            puts "Model: $model, Version: $version"
                            upgrade_ap $ip $model $version $fw_server_ip $fwname
                        }
                    }
                }
                # 0-1-2) Directly enters enable mode
                -re "ruckus#" {  ;# Directly to ruckus# after enable
                    send "ap-mode\r"
                    expect -re "ap-mode" {
                        send "get version\r"
                        expect {
                            -re {Ruckus\s(\S+)\s.*\nVersion:\s(\S+)} {
                                set model $expect_out(1,string)
                                set version $expect_out(2,string)
                                puts "Model: $model, Version: $version"
                                upgrade_ap $ip $model $version $fw_server_ip $fwname
                            }
                        }
                    }
                }			
            }		
        }	
        # 1) Correct password
        -re "rkscli" {
            send "get version\r"
            expect {
                -re {Ruckus\s(\S+)\s.*\nVersion:\s(\S+)} {
                    set model $expect_out(1,string)
                    set version $expect_out(2,string)
                    puts "Model: $model"
                    puts "Version: $version"
                    upgrade_ap $ip $model $version $fw_server_ip $fwname
                }
            }
        }
        # 2) Incorrect password, try sp-admin
        -re "Login incorrect" {
            expect -re "Please login"
            puts "(Login failed for $ip, trying with sp-admin.)"
            sleep 2
            send "$user\r"
            sleep 2
            expect -re "password"
            send "sp-admin\r"
            expect {
                # 2-1-1) sp-admin correct
                -re "rkscli" {
                    send "get version\r"
                    expect {
                        -re {Ruckus\s(\S+)\s.*\nVersion:\s(\S+)} {
                            set model $expect_out(1,string)
                            set version $expect_out(2,string)
                            puts "Model: $model"
                            puts "Version: $version"
                            upgrade_ap $ip $model $version $fw_server_ip $fwname
                        }
                    }
                }
                # 2-1-2) sp-admin prompts for new password (reset AP)
                -re "New password" {
                    send "ruckus12#$\r"
                    expect -re "Confirm password"
                    send "ruckus12#$\r"
                    # Request login again after confirming password
                    expect -re "Please login"
                    send "super\r"
                    expect -re "password"
                    # Force apply ruckus12#$ password
                    send "ruckus12#$\r"
                    # Wait for rkscli after confirming password
                    expect -re "rkscli" {
                        send "get version\r"
                        expect {
                            -re {Ruckus\s(\S+)\s.*\nVersion:\s(\S+)} {
                                set model $expect_out(1,string)
                                set version $expect_out(2,string)
                                puts "Model: $model"
                                puts "Version: $version"
                                upgrade_ap $ip $model $version $fw_server_ip $fwname
                            }
                        }
                    }	
                }
                # 2-1-3) sp-admin incorrect, try ruckus12#$
                -re "Login incorrect" {
                    expect -re "Please login"
                    puts "(Login failed for $ip, trying with ruckus12#$)"
                    sleep 2
                    send "$user\r"
                    sleep 2
                    expect -re "password"
                    send "ruckus12#$\r"
                    expect {
                        # 2-1-3-1) ruckus12#$ correct
                        -re "rkscli" {
                            send "get version\r"
                            expect {
                                -re {Ruckus\s(\S+)\s.*\nVersion:\s(\S+)} {
                                    set model $expect_out(1,string)
                                    set version $expect_out(2,string)
                                    puts "Model: $model"
                                    puts "Version: $version"
                                    upgrade_ap $ip $model $version $fw_server_ip $fwname
                                }
                            }
                        }
                        # 2-1-3-2) ruckus12#$ incorrect, move to next device
                        -re "Login incorrect\r\n\r\nPlease login:" {
                            set status_msg "Multiple login failures, moving to next device."
                            puts "($ip $status_msg)"
                            log_result $ip $status_msg
                            continue
                        }
                    }
                }
            }
        }
        # 3) New password prompt after initial password attempt (reset AP)
        -re "New password" {
            send "ruckus12#$\r"
            expect -re "Confirm password"
            send "ruckus12#$\r"
            # May request login again after confirming password
            expect -re "Please login"
            send "$user\r"
            expect -re "password"
            send "ruckus12#$\r"
            # Wait for rkscli after confirming password
            expect -re "rkscli" {
                send "get version\r"
                expect {
                    -re {Ruckus\s(\S+)\s.*\nVersion:\s(\S+)} {
                        set model $expect_out(1,string)
                        set version $expect_out(2,string)
                        puts "Model: $model"
                        puts "Version: $version"
                        upgrade_ap $ip $model $version $fw_server_ip $fwname
                    }
                }
            }
        }
    }
}
close $fp
close $result_fp ;# Close result file handle

puts "\n※ Script execution completed.\n"
