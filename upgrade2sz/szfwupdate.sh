#!/usr/bin/expect -f
# exp_internal 0 for debugging
# Remove comment for debugging
#exp_internal 1
# Remove comment for debugging
#log_file szfw_debug.log

# Terminal environment setting
#set env(TERM) dumb
# Wait time before every command
set timeout 5

# ✅ Receive 4 arguments from PHP
set filename [lindex $argv 0]            ;# CSV Path
set firmwareVersion [lindex $argv 1]    ;# Firmware Version
set api_sz_ip [lindex $argv 2]          ;# SmartZone IP for API access (from form)
set result_file_path [lindex $argv 3]   ;# Result File Path

# ✅ Check number of arguments (Error prevention)
if {[llength $argv] < 4} {
    puts "Error: Missing arguments. Usage: ./ufwupdate.sh <csv_path> <firmware_version> <api_sz_ip> <result_file_path>"
    exit 1
}

# =========================================================
# ✅ Insert UTF-8 BOM (Unicode \uFEFF method)
# 1. Open the result file in write mode ('w').
set result_fp [open $result_file_path w]

# 2. Explicitly set channel encoding to UTF-8. (Most important) - Comment out on centos, Rocky.
#fconfigure $result_fp -encoding utf-8

# 3. Write the BOM character \uFEFF. - Use \xEF\xBB\xBF on centos, Rocky.
#    Tcl outputs this character as 3 bytes (0xEF 0xBB 0xBF) which Excel recognizes in a UTF-8 channel.
#puts -nonewline $result_fp "\uFEFF"
puts -nonewline $result_fp "\xEF\xBB\xBF"

# 4. Write the CSV header. The result CSV header is also updated to 8 columns (Script result is IP,Status,Serial,MAC_Address,User,Pass)
puts $result_fp "IP,Status,Serial,MAC_Address,User,Pass"

# 5. Close the file and reopen it in append mode ('a') for subsequent operations
close $result_fp
set result_fp [open $result_file_path a]
# =========================================================


# Result logging function
proc log_result {ip status_msg serial mac_addr user pass} {
    global result_fp
    puts $result_fp "$ip,$status_msg,$serial,$mac_addr,$user,$pass" ;# Keep comma separator
}

# Define firmware upgrade and configuration change logic in a separate procedure
# ✅ Change: Add IP/SZ/Hostname change logic upon successful firmware upgrade and remove set factory
proc upgrade_ap {ip model version firmwareVersion new_ip subnet gw config_sz hostname api_sz_ip user pass} {
    global result_list
    set mac_addr ""
    set serial ""
    set status_msg ""
    # Extract Serial and MAC address
    send "get boarddata\r"
    sleep 1
    expect {
        -re {Serial#:\s+([0-9:]+).*base\s([0-9A-Fa-f:]+)} {
            set serial $expect_out(1,string)
            set mac_addr $expect_out(2,string)
            puts "Serial: $serial"
            puts "mac_addr: $mac_addr"
        }
        timeout {
            puts "(Timeout occurred while fetching boarddata for $ip.)"
            set status_msg "Timeout occurred while fetching boarddata for $ip."
            lappend result_list "$ip,$status_msg,$serial,$mac_addr,$user,$pass"
            return 0
        }
    }
    expect {
        -re "rkscli" {
            puts "(Serial#: $serial, MAC Address: $mac_addr for $ip)"
        }
        timeout {
            puts "rkscli prompt timeout for $ip."
            set status_msg "rkscli prompt timeout for $ip."
            lappend result_list "$ip,$status_msg,$serial,$mac_addr,$user,$pass"
            return 0
        }
    }
    
    # 1. Start firmware upgrade (Using API SZ IP)
    puts "Upgrading AP $ip ($model) to $firmwareVersion via SZ API (Host: $api_sz_ip)"
    
    send "fw set proto HTTPS\r"
	expect -re "OK\r\n.*rkscli"
    send "fw set port 443\r"
	expect -re "OK\r\n.*rkscli"
    send "fw set host $api_sz_ip\r"
	expect -re "OK\r\n.*rkscli"
    send "fw set control wsg/firmware/$model\_$firmwareVersion.rcks\r"
	expect -re "OK\r\n.*rkscli"
	send "fw update\r"
    set timeout 5
    
    expect {
        -re {.*Control File Download Error.*|.*cannot connect to remote host.*rkscli} {
            set status_msg "Failed to connect to SmartZone($api_sz_ip) or locate the firmware file for $ip."
            puts "$ip $status_msg"
            # Do not proceed with AP configuration if firmware upgrade fails
            log_result $ip $status_msg $serial $mac_addr $user $pass
            set timeout 5
            return 1
        }
        -re {(.|\r)*needs a reboot(.|\r)*} {
            set status_msg "Upgrade failed for $ip. Attempting AP reboot. Please try the upgrade again."
            puts "$ip $status_msg"
            send "reboot\r"; expect "OK"
            puts "Rebooting $ip."
            log_result $ip $status_msg $serial $mac_addr $user $pass
            return 1
        }
        timeout {}
    }

    set timeout 180
    
    expect {
	    -re {(.|\r)*needs a reboot(.|\r)*} {
            set status_msg "Upgrade error on $ip. Please reboot the AP and try the upgrade again. Rebooting now."
            puts "$ip $status_msg"
            send "reboot\r"; expect "OK"
            puts "Rebooting $ip."
            log_result $ip $status_msg $serial $mac_addr $user $pass
            return 1
        }
		-re {.*No update.*OK} {
            set status_msg "$ip is already the same version. Moving to the next device."
			puts "$ip $status_msg"
			log_result $ip $status_msg $serial $mac_addr $user $pass
			return 1
		}
		
        -re {fw\([0-9]+\) : Completed} {
            # 2. Change configuration upon successful firmware upgrade (sz_devicename_changeip logic)
            puts "($ip firmware update complete. Starting AP configuration change.)"
            set timeout 10

            # 2-1. Set SCG IP
            send "set scg ip $config_sz\r"
            expect -re "OK\r\n.*rkscli" { puts "($ip SZ IP setting complete: $config_sz)" } timeout { puts "($ip SZ IP setting timeout/failure.)" }
            
            # 2-2. Set device name
            send "set device-name $hostname\r"
            expect -re "OK\r\n.*rkscli" { puts "($ip hostname setting complete: $hostname)" } timeout { puts "($ip hostname setting timeout/failure.)" }
            
            # 2-3. Set IP address (Connection loss expected here) - Skipping since communication is lost when setting static IP, consider changing logic later.
            set timeout 5
            send "set ipaddr wan $new_ip $subnet $gw\r"
            
            # Initialize final status message regardless of IP change success
            set status_msg "$ip firmware upgrade. Configuration change complete." 

            expect {
               -re "OK\r\n.*rkscli" {
                    puts "($ip IP change complete.)"
                }
               timeout {
                    puts "$ip connection is interrupted after IP change."
                    set status_msg "$ip firmware upgrade (vers.: $firmwareVersion)| SZ - $api_sz_ip | IP- $new_ip $subnet $gw configuration change complete. Please perform a manual reboot."
                    log_result $ip $status_msg $serial $mac_addr $user $pass
                    return 0
                }
                eof {
                    puts "($ip IP change command sent, connection closed.)"
                }
            }
            set timeout 5
            
            # 🚩 3. Final Reboot (Applying IP comparison conditional statement)
            if {$ip == $new_ip} {
                puts "($ip and $new_ip are the same. Attempting reboot.)"
                
                # Execute reboot logic
                send "reboot\r"
                expect {
                    -re "OK\r\n.*rkscli" {
                        # reboot command
                        set status_msg "$ip firmware upgrade (vers.: $firmwareVersion) | SZ - $api_sz_ip | IP- $new_ip $subnet $gw configuration change and reboot command execution complete."
                        puts $status_msg
                    }
                    timeout {
                        set status_msg "$ip firmware upgrade. Configuration change, reboot command execution, and timeout."
                        puts $status_msg
                    }
                    eof {
                        set status_msg "$ip firmware upgrade. Configuration change, reboot command sent, connection closed."
                        puts $status_msg
                    }
                }
            } else {
                # Skip reboot if $ip and $new_ip are different
                puts "($ip and $new_ip are different. IP has been changed, skipping reboot and moving to the next device.)"
                set status_msg "$ip firmware upgrade (vers.: $firmwareVersion)| SZ - $api_sz_ip | IP- $new_ip $subnet $gw configuration change complete. Reboot skipped."
                puts $status_msg
            }

            # Final log record and exit
            log_result $ip $status_msg $serial $mac_addr $user $pass
            return 0
        }
		
        timeout {
            set status_msg "$ip firmware update did not complete within 180 seconds. Communication and file availability need to be checked."
            puts "$ip $status_msg"
            #log_result $ip $status_msg $serial $mac_addr (Arguments should be modified as Serial/MAC may not exist)
            log_result $ip $status_msg $serial $mac_addr $user $pass
            set timeout 5
            return 1
        }
    }
}

# Open file
set fp [open $filename r]
set index 0

while {[gets $fp line] != -1} {
    # Clean line endings
    set line [string trim [regsub -all {\r\n|\r|\n} $line ""]]
    
    # Skip header
    if {$index == 0} {
        puts "Firmware Version: $firmwareVersion, SmartZone API IP: $api_sz_ip"
        incr index
        continue
    }
    
    # Split fields by comma
    if {[catch {set fields [split $line ","]} err]} {
        puts "Error: Failed to parse line $index: '$line' ($err)"
        continue
    }

    # Check for empty lines or insufficient fields
    if {[llength $fields] < 8} {
        # Check for all-empty fields (e.g., ',,,,,,,')
        set all_empty 1
        foreach field $fields {
            if {[string trim $field] != ""} {
                set all_empty 0
                break
            }
        }
        if {$all_empty} {
            continue
        }
        puts "Error: Line [expr {$index + 1}] - Number of fields is less than 8 ([llength $fields] fields): '$line'"
        incr index
        continue
    }
    incr index

    # ✅ Specify 8 fields
    set ip [string trim [lindex $fields 0]]         ;# current_ip
    set user [string trim [lindex $fields 1]]       ;# user
    set pass [string trim [lindex $fields 2]]       ;# pass
    set new_ip [string trim [lindex $fields 3]]     ;# new_IP
    set subnet [string trim [lindex $fields 4]]     ;# subnet
    set gw [string trim [lindex $fields 5]]         ;# g/w
    set config_sz [string trim [lindex $fields 6]]  ;# sz (SmartZone IP to configure on AP)
    set hostname [string trim [lindex $fields 7]]   ;# hostname

    # (SSH connection and login logic below is mostly the same as before)
    
    puts "Processing: Starting firmware upgrade for $ip..."

    # SSH connection
    set timeout 10
    spawn ssh -legacy -t -o StrictHostKeyChecking=no -o HostKeyAlgorithms=+ssh-rsa -o ForwardX11=no $user@$ip
    sleep 2
    
    # Login attempt (Same expect block below)
    expect {
        "ssh: connect to host" {
            # ... (Connection failure logic)
            set status_msg "Cannot connect to $ip."
            puts "($ip $status_msg Moving to the next device.)"
            log_result $ip $status_msg "" "" $user $pass
            close
            wait
            continue
        }
		-re ".*Connection closed.*" {
		    set status_msg "$ip connection lost. The device seems to be rebooting."
            puts "($ip $status_msg Moving to the next device.)"
            log_result $ip $status_msg "" "" $user $pass
            close
            wait
            continue
		}
        timeout {
            # ... (Timeout logic)
            set status_msg "$ip connection timed out (10 seconds)."
            puts "($ip $status_msg Moving to the next device.)"
            log_result $ip $status_msg "" "" $user $pass
            close
            wait
            continue
        }
        -re "Please login" {
            send "$user\r"
        }
    }
    	
    # Enter password
    expect -re "password"
	send "$pass\r"
	
	# Scenarios start
    set current_user $user
    set current_pass $pass
	expect {
	#0) If it's Unleashed
			-re "(ruckus>|ruckus#)" {
				puts "($ip -> Unleashed is not applicable to this script. Moving to the next device.)"
				log_result $ip "Unleashed script application impossible" "" "" $current_user $current_pass
				close
				wait
				continue
			}

	# 1) If the entered password is correct
			-re "rkscli" {
				send "get version\r"
				expect {
					-re {Ruckus\s(\S+)\s.*\nVersion:\s(\S+)} {
						set model $expect_out(1,string)
						set version $expect_out(2,string)
						puts "Model: $model, Version: $version"
						# ✅ The config argument must be passed.
						upgrade_ap $ip $model $version $firmwareVersion $new_ip $subnet $gw $config_sz $hostname $api_sz_ip $user $pass
					}
				}
				close
				wait
				continue
			}
	# 2) If the entered password is wrong	
			#2-1) Attempt with sp-admin
			-re "Login incorrect" {
				expect -re "Please login"
				puts "($ip login failed, attempting with sp-admin.)"
				set current_pass "sp-admin"
				send "$current_user\r"
				expect -re "password"
				send "$current_pass\r"
				expect {
				#2-1-1) If sp-admin is correct
					-re "rkscli" {
						send "get version\r"
						expect {
							-re {Ruckus\s(\S+)\s.*\nVersion:\s(\S+)} {
								set model $expect_out(1,string)
								set version $expect_out(2,string)
								puts "Model: $model, Version: $version"
								upgrade_ap $ip $model $version $firmwareVersion $new_ip $subnet $gw $config_sz $hostname $api_sz_ip $user $pass
							}
						}
						close
						wait
						continue
					}
				# 2-1-2) sp-admin prompts for new password (reset AP)
					-re "New password" {
						set current_pass "Ruckus12#$"
						send "$current_pass\r"
						expect -re "Confirm password"
						send "$current_pass\r"
						expect -re "Please login"
						send "$current_user\r"
						expect -re "password"
						send "$current_pass\r"
						expect -re "rkscli" {
									send "get version\r"
									expect {
										-re {Ruckus\s(\S+)\s.*\nVersion:\s(\S+)} {
											set model $expect_out(1,string)
											set version $expect_out(2,string)
											puts "Model: $model, Version: $version"
											upgrade_ap $ip $model $version $firmwareVersion $new_ip $subnet $gw $config_sz $hostname $api_sz_ip $user $pass
										}
									}
						}	
						close
						wait
						continue
					}
				# 2-1-3) sp-admin incorrect, try Ruckus12#$
					-re "Login incorrect" {
						expect -re "Please login"
						puts "($ip login failed, attempting with Ruckus12#$.)"
						set current_pass "Ruckus12#$"
						send "$current_user\r"
						expect -re "password"
						send "$current_pass\r"
						# 2-1-3-1) Ruckus12#$ correct
						expect {
								-re "rkscli" {
									send "get version\r"
									expect {
										-re {Ruckus\s(\S+)\s.*\nVersion:\s(\S+)} {
											set model $expect_out(1,string)
											set version $expect_out(2,string)
											puts "Model: $model, Version: $version"
											upgrade_ap $ip $model $version $firmwareVersion $new_ip $subnet $gw $config_sz $hostname $api_sz_ip $user $pass
										}
									}
									close
									wait
									continue
								}
						# 2-1-3-2) ruckus12#$ incorrect
								-re "Login incorrect\r\n\r\nPlease login:" {
									set status_msg "$ip login failed repeatedly, moving to the next device."
									puts "($ip $status_msg)"
									log_result $ip $status_msg "" "" $current_user $current_pass
									close
									wait
									continue
								}
						}
					}
				}
			}
			# 3) New password prompt (reset AP)
            -re "New password" {
                set current_pass "Ruckus12#$"
                send "$current_pass\r"
                expect -re "Confirm password"
                send "$current_pass\r"
                expect -re "Please login"
                send "$current_user\r"
                expect -re "password"
                send "$current_pass\r"
                expect -re "rkscli" {
							send "get version\r"
							expect {
								-re {Ruckus\s(\S+)\s.*\nVersion:\s(\S+)} {
									set model $expect_out(1,string)
									set version $expect_out(2,string)
									puts "Model: $model, Version: $version"
									upgrade_ap $ip $model $version $firmwareVersion $new_ip $subnet $gw $config_sz $hostname $api_sz_ip $user $pass
									
								}
							}
				}
				close
				wait
				continue
			}
	}
}

close $fp
close $result_fp