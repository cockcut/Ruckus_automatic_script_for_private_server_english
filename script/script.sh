#!/usr/bin/expect -f
# Debugging: enable for detailed output
#set DEBUG_LOG 1
#exp_internal 1
# log_file script_debug.log
# Set global timeout for commands
set timeout 5

# Get operation and filename from arguments
set operation [lindex $argv 0]
set filename [lindex $argv 1]

# Validate input arguments
if {$operation == "" || $filename == ""} {
    puts "Error: operation or filename not provided."
    exit 1
}

# Check if CSV file exists
if {![file exists $filename]} {
    puts "Error: CSV file $filename does not exist."
    exit 1
}

# Initialize log and result files based on operation
set log_file "script_${operation}.log"
set result_file "script_${operation}_result.csv"
file delete $log_file
set result_list [list]

# Process device based on operation
proc process_device {ip user pass new_ip subnet gw sz hostname operation} {
    global result_list
    set mac_addr ""
    set serial ""
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
    }
    expect {
        -re "rkscli" {
            puts "(Serial# of $ip: $serial, MAC address: $mac_addr)"
        }
    }

    # Operation-specific commands
    switch $operation {
        "connect_sz" {
            puts "(Setting SZ IP of $ip to $sz.)"
            send "set scg ip $sz\r"
            expect {
                -re "OK\r\n.*rkscli" {
                    puts "(Changed SZ IP of $ip to $sz.)"
                    if {[info exists ::DEBUG_LOG]} {
                        puts "Debug: set scg ip $sz command successful: $expect_out(0,string)"
                    }
                    # Verify SZ IP
                    send "get scg\r"
                    expect {
                        -re "Server List:.*$sz.*rkscli" {
                            puts "(Verified changed SZ IP of $ip to $sz.)"
                            if {[info exists ::DEBUG_LOG]} {
                                puts "Debug: get scg output: $expect_out(0,string)"
                            }
                        }
                    }
                }
                timeout {
                    puts "(Timeout occurred while setting SZ IP of $ip.)"
                    return 0
                }
            }
        }
        "reboot" {
            puts "(Rebooting $ip.)"
            set timeout 5
            send "reboot\r"
            expect {
                -re "OK\r\n.*rkscli" {
                    puts "(Reboot command executed for $ip.)"
                    if {[info exists ::DEBUG_LOG]} {
                        puts "Debug: rkscli prompt received after reboot command."
                    }
                }
            }
        }
        "factory_reset" {
            puts "(Factory resetting AP $ip.)"
            set timeout 10
            send "set factory\r"
            expect {
                -re "OK\r\n.*rkscli" {
                    puts "(Factory reset command completed for AP $ip.)"
                    if {[info exists ::DEBUG_LOG]} {
                        puts "Debug: set factory command successful: $expect_out(0,string)"
                    }
                }
                timeout {
                    puts "(Timeout occurred during factory reset of $ip.)"
                    return 0
                }
            }
            send "reboot\r"
            expect {
                -re "rkscli" {
                    puts "(Factory reset completed and reboot command executed for AP $ip.)"
                    if {[info exists ::DEBUG_LOG]} {
                        puts "Debug: rkscli prompt received after reboot command."
                    }
                }
                timeout {
                    puts "(Timeout occurred during reboot of $ip.)"
                    return 0
                }
                eof {
                    puts "(Reboot command sent for $ip, connection closed.)"
                    if {[info exists ::DEBUG_LOG]} {
                        puts "Debug: Connection closed due to reboot command."
                    }
                }
            }
        }
        "changeip" {
            puts "(Changing $ip to $new_ip.)"
            set timeout 5
            send "set ipaddr wan $new_ip $subnet $gw\r"
            expect {
                -re "OK\r\n.*rkscli" {
                    puts "(Changed $ip to $new_ip.)"
                    if {[info exists ::DEBUG_LOG]} {
                        puts "Debug: set ipaddr wan $new_ip $subnet $gw command successful: $expect_out(0,string)"
                    }
                    # Skip get ipaddr due to potential connection loss
                    puts "(Verification skipped after IP change for $ip, possible connection loss.)"
                    if {[info exists ::DEBUG_LOG]} {
                        puts "Debug: get ipaddr skipped, connection stability uncertain after IP change."
                    }
                }
                timeout {
                    puts "($ip IP change interrupted, moving to next device.)"
#                    return 0
                }
                eof {
                    puts "(IP change command sent for $ip, connection closed.)"
                    if {[info exists ::DEBUG_LOG]} {
                        puts "Debug: Connection closed due to set ipaddr wan command."
                    }
                }
            }
            set timeout 5
        }
        "devicename" {
            puts "(Changing hostname of $ip to \"$hostname\".)"
            set timeout 10
            send "set device-name $hostname\r"
            expect {
                -re "OK\r\n.*rkscli" {
                    puts "(Changed hostname of $ip to \"$hostname\".)"
                    if {[info exists ::DEBUG_LOG]} {
                        puts "Debug: set device-name $hostname command successful: $expect_out(0,string)"
                    }
                    # Verify device name
					set timeout 3
                    send "get device-name\r"
                    expect {
                        -re "device name.:.*$hostname.*rkscli|Device Name.:.*$hostname.*rkscli" {
                            puts "(Verified changed hostname of $ip to \"$hostname\".)"
                            if {[info exists ::DEBUG_LOG]} {
                                puts "Debug: get device-name output: $expect_out(0,string)"
                            }
                        }
                        timeout {
                            puts "(Timeout occurred while verifying hostname setting for $ip.)"
                            return 0
                        }
                    }
                }
            }
        }
        "sz_devicename_changeip" {
            puts "(Setting SZ IP of $ip to $sz, hostname to \"$hostname\", and IP to $new_ip.)"
            set timeout 10
            # Set SCG IP
            send "set scg ip $sz\r"
            expect {
                -re "OK\r\n.*rkscli" {
                    puts "(SZ IP setting completed for $ip.)"
                    if {[info exists ::DEBUG_LOG]} {
                        puts "Debug: set scg ip $sz command successful: $expect_out(0,string)"
                    }
                    # Verify SZ IP
                    send "get scg\r"
                    expect {
                        -re "Server List:.*$sz.*rkscli" {
                            puts "(SZ IP of $ip verified as $sz.)"
                            if {[info exists ::DEBUG_LOG]} {
                                puts "Debug: get scg output: $expect_out(0,string)"
                            }
                        }
                    }
                }
            }
            # Set device name
            send "set device-name $hostname\r"
            expect {
                -re "OK\r\n.*rkscli" {
                    puts "(Hostname setting completed for $ip.)"
                    if {[info exists ::DEBUG_LOG]} {
                        puts "Debug: set device-name $hostname command successful: $expect_out(0,string)"
                    }
                    # Verify device name
                    send "get device-name\r"
                    expect {
                        -re "Device Name:.*$hostname.*rkscli" {
                            puts "(Hostname of $ip verified as \"$hostname\".)"
                            if {[info exists ::DEBUG_LOG]} {
                                puts "Debug: get device-name output: $expect_out(0,string)"
                            }
                        }
                    }
                }
            }
            # Set IP address
            set timeout 5
            send "set ipaddr wan $new_ip $subnet $gw\r"
            expect {
                -re "OK\r\n.*rkscli" {
                    puts "(IP change completed for $ip.)"
                    if {[info exists ::DEBUG_LOG]} {
                        puts "Debug: set ipaddr wan $new_ip $subnet $gw command successful: $expect_out(0,string)"
                    }
                    # Skip get ipaddr due to potential connection loss
                    puts "(Verification skipped after IP change for $ip, possible connection loss.)"
                    if {[info exists ::DEBUG_LOG]} {
                        puts "Debug: get ipaddr skipped, connection stability uncertain after IP change."
                    }
                }
                timeout {
                    puts "(Timeout occurred during IP change for $ip.)"
                    return 0
                }
                eof {
                    puts "(IP change command sent for $ip, connection closed.)"
                    if {[info exists ::DEBUG_LOG]} {
                        puts "Debug: Connection closed due to set ipaddr wan command."
                    }
                }
            }
            set timeout 5
        }
        default {
            puts "Unknown operation: $operation"
            exit 1
        }
    }

    # Append to result list
    lappend result_list "$new_ip,$subnet,$gw,$sz,$hostname,$serial,$mac_addr,$ip,$user,$pass"
    puts "(Moving to next device.)"
    return 1
}

# Open CSV file
if {[catch {set fp [open $filename r]} err]} {
    puts "Error: Unable to open CSV file $filename: $err"
    exit 1
}

set index 0
while {[gets $fp line] >= 0} {
    # Clean line endings (handle Windows \r\n)
    set line [string trim [regsub -all {\r\n|\r|\n} $line ""]]
    
    # Skip empty lines
    if {$line == ""} {
        if {[info exists ::DEBUG_LOG]} {
            puts "Debug: Skipping empty line (line [expr {$index + 1}])."
        }
        continue
    }

    if {$index == 0} {
        incr index
        continue
    }
    incr index

    # Log the raw line for debugging
    if {[info exists ::DEBUG_LOG]} {
        puts "Debug: Line $index - Raw: '$line'"
    }

    # Split fields by comma
    if {[catch {set fields [split $line ","]} err]} {
        puts "Error: Failed to parse line $index: '$line' ($err)"
        continue
    }

    # Check for all-empty fields (e.g., ',,,,,,,')
    set all_empty 1
    foreach field $fields {
        if {[string trim $field] != ""} {
            set all_empty 0
            break
        }
    }
    if {$all_empty} {
        if {[info exists ::DEBUG_LOG]} {
            puts "Debug: Line $index - All fields are empty: '$line'"
        }
        continue
    }

    # Log fields for debugging
    if {[info exists ::DEBUG_LOG]} {
        puts "Debug: Line $index - Fields: [join $fields "|"]"
    }

    # Validate number of fields
    if {[llength $fields] != 8} {
        puts "Error: Line $index - Number of fields is not 8 ([llength $fields] fields): '$line'"
        continue
    }

    # Assign variables with trimming
    set ip [string trim [lindex $fields 0]]
    set user [string trim [lindex $fields 1]]
    set pass [string trim [lindex $fields 2]]
    set new_ip [string trim [lindex $fields 3]]
    set subnet [string trim [lindex $fields 4]]
    set gw [string trim [lindex $fields 5]]
    set sz [string trim [lindex $fields 6]]
    set hostname [string trim [lindex $fields 7]]

    # Validate IP addresses (basic check)
    if {$ip == "" || ($operation != "connect_sz" && $operation != "reboot" && $operation != "factory_reset" && $new_ip == "")} {
        puts "Error: Line $index - IP address is empty: '$line'"
        continue
    }

    # Validate SZ IP for connect_sz and sz_devicename_changeip
    if {($operation == "connect_sz" || $operation == "sz_devicename_changeip") && $sz == ""} {
        puts "Error: Line $index - SZ IP is empty: '$line'"
        continue
    }

    # Validate hostname for devicename and sz_devicename_changeip
    if {($operation == "devicename" || $operation == "sz_devicename_changeip") && $hostname == ""} {
        puts "Error: Line $index - hostname is empty: '$line'"
        continue
    }

    # Print processing message
    puts "Processing: $ip (performing $operation...)"

    # SSH connection
	set timeout 5
    spawn ssh -o StrictHostKeyChecking=no -o HostKeyAlgorithms=+ssh-rsa -o ForwardX11=no $user@$ip
    sleep 2

    # Login attempt
    expect {
        "Are you sure you want to continue connecting (yes/no)?" {
            send "yes\r"
            expect {
                -re "password" {
                    send "$pass\r"
                }
                timeout {
                    puts "(Timeout waiting for password prompt for $ip.)"
                    close
                    wait
                    continue
                }
            }
        }
        -re "password" {
            send "$pass\r"
        }
        "ssh: connect to host" {
            puts "(Unable to connect to $ip. Moving to next device.)"
            close
            wait
            continue
        }
		"Connection closed*" {
		    puts "(Connection closed by $ip. Moving to next device.)"
            close
            wait
            continue
		}
        timeout {
            puts "(Connection timeout for $ip. Moving to next device.)"
            close
            wait
            continue
        }
        -re "Please login" {
            send "$user\r"
            expect {
                -re "password" {
                    send "$pass\r"
                }
                timeout {
                    puts "(Timeout waiting for password prompt for $ip.)"
                    close
                    wait
                    continue
                }
            }
        }
    }

    # Handle login scenarios
    expect {
        # 0) Unleashed device
        -re "(ruckus>|ruckus#)" {
            puts "($ip -> Unleashed devices are not supported by this script. Moving to next device.)"
            close
            wait
            continue
        }
        # 1) Correct password
        -re "rkscli" {
            sleep 1
            set result [process_device $ip $user $pass $new_ip $subnet $gw $sz $hostname $operation]
            close
            wait
            if {$result == 0} {
                puts "(Error occurred while processing $ip, moving to next device.)"
            }
            continue
        }
        # 2) Incorrect password, try sp-admin
        -re "Login incorrect" {
            expect {
                -re "Please login" {
                    puts "(Login failed for $ip, trying with sp-admin.)"
                    sleep 2
                    send "$user\r"
                    expect {
                        -re "password" {
                            send "sp-admin\r"
                        }
                        timeout {
                            puts "(Timeout waiting for sp-admin login prompt for $ip.)"
                            close
                            wait
                            continue
                        }
                    }
                    expect {
                        # 2-1) sp-admin correct
                        -re "rkscli" {
                            sleep 1
                            set result [process_device $ip $user $pass $new_ip $subnet $gw $sz $hostname $operation]
                            close
                            wait
                            if {$result == 0} {
                                puts "(Error occurred while processing $ip, moving to next device.)"
                            }
                            continue
                        }
                        # 2-2) sp-admin prompts for new password (reset AP)
                        -re "New password" {
                            send "ruckus12#$\r"
                            expect {
                                -re "Confirm password" {
                                    send "ruckus12#$\r"
                                }
                                timeout {
                                    puts "(Timeout confirming password for $ip.)"
                                    close
                                    wait
                                    continue
                                }
                            }
                            expect {
                                -re "Please login" {
                                    send "$user\r"
                                    expect {
                                        -re "password" {
                                            send "ruckus12#$\r"
                                        }
                                        timeout {
                                            puts "(Timeout waiting for new password login prompt for $ip.)"
                                            close
                                            wait
                                            continue
                                        }
                                    }
                                    expect {
                                        -re "rkscli" {
                                            sleep 1
                                            set result [process_device $ip $user $pass $new_ip $subnet $gw $sz $hostname $operation]
                                            close
                                            wait
                                            if {$result == 0} {
                                                puts "(Error occurred while processing $ip, moving to next device.)"
                                            }
                                            continue
                                        }
                                        timeout {
                                            puts "(Timeout waiting for rkscli prompt for $ip.)"
                                            close
                                            wait
                                            continue
                                        }
                                    }
                                }
                                timeout {
                                    puts "(Timeout waiting for new login prompt for $ip.)"
                                    close
                                    wait
                                    continue
                                }
                            }
                        }
                        # 2-3) sp-admin incorrect, try ruckus12#$
                        -re "Login incorrect" {
                            expect {
                                -re "Please login" {
                                    puts "(Login failed for $ip, trying with ruckus12#$.)"
                                    sleep 2
                                    send "$user\r"
                                    expect {
                                        -re "password" {
                                            send "ruckus12#$\r"
                                        }
                                        timeout {
                                            puts "(Timeout waiting for ruckus12#$ login prompt for $ip.)"
                                            close
                                            wait
                                            continue
                                        }
                                    }
                                    expect {
                                        # 2-3-1) ruckus12#$ correct
                                        -re "rkscli" {
                                            sleep 1
                                            set result [process_device $ip $user $pass $new_ip $subnet $gw $sz $hostname $operation]
                                            close
                                            wait
                                            if {$result == 0} {
                                                puts "(Error occurred while processing $ip, moving to next device.)"
                                            }
                                            continue
                                        }
                                        # 2-3-2) ruckus12#$ incorrect
                                        -re "Login incorrect" {
                                            puts "(Multiple login failures for $ip, moving to next device.)"
                                            close
                                            wait
                                            continue
                                        }
                                        # 2-3-3) ruckus12#$ with unleashed
                                        -re "(ruckus>|ruckus#)" {
                                            puts "($ip -> Unleashed devices are not supported by this script. Moving to next device.)"
                                            close
                                            wait
                                            continue
                                        }
                                        timeout {
                                            puts "(Timeout after ruckus12#$ login for $ip.)"
                                            close
                                            wait
                                            continue
                                        }
                                    }
                                }
                                timeout {
                                    puts "(Timeout after sp-admin login for $ip.)"
                                    close
                                    wait
                                    continue
                                }
                            }
                        }
                        # 2-4) sp-admin with unleashed
                        -re "(ruckus>|ruckus#)" {
                            puts "($ip -> Unleashed devices are not supported by this script. Moving to next device.)"
                            close
                            wait
                            continue
                        }
                        timeout {
                            puts "(Timeout during sp-admin login processing for $ip.)"
                            close
                            wait
                            continue
                        }
                    }
                }
                timeout {
                    puts "(Timeout waiting for login prompt for $ip.)"
                    close
                    wait
                    continue
                }
            }
        }
        # 3) New password prompt (reset AP)
        -re "New password" {
            send "ruckus12#$\r"
            expect {
                -re "Confirm password" {
                    send "ruckus12#$\r"
                }
                timeout {
                    puts "(Timeout confirming password for $ip.)"
                    close
                    wait
                    continue
                }
            }
            expect {
                -re "Please login" {
                    send "$user\r"
                    expect {
                        -re "password" {
                            send "ruckus12#$\r"
                        }
                        timeout {
                            puts "(Timeout waiting for new password login prompt for $ip.)"
                            close
                            wait
                            continue
                        }
                    }
                    expect {
                        -re "rkscli" {
                            sleep 1
                            set result [process_device $ip $user $pass $new_ip $subnet $gw $sz $hostname $operation]
                            close
                            wait
                            if {$result == 0} {
                                puts "(Error occurred while processing $ip, moving to next device.)"
                            }
                            continue
                        }
                        timeout {
                            puts "(Timeout waiting for rkscli prompt for $ip.)"
                            close
                            wait
                            continue
                        }
                    }
                }
                timeout {
                    puts "(Timeout waiting for new login prompt for $ip.)"
                    close
                    wait
                    continue
                }
            }
        }
        timeout {
            puts "(Timeout during login processing for $ip.)"
            close
            wait
            continue
        }
    }
}

close $fp

# Write results to CSV
if {[catch {set result_fp [open $result_file w]} err]} {
    puts "Error: Unable to open result file $result_file: $err"
    exit 1
}
puts $result_fp "static_IP,Subnet,GW,SZ,Hostname,Serial,MAC_Address,old_dhcp_IP,User,Pass"
foreach result $result_list {
    puts $result_fp $result
}
close $result_fp

puts "\n※ Script execution completed.\n"
