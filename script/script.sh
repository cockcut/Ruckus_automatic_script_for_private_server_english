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
            set status_msg "(Timeout occurred while fetching boarddata for $ip.)"
            lappend result_list "$ip,$status_msg,$serial,$mac_addr,$user,$pass"
            return 0
        }
    }
    expect {
        -re "rkscli" {
            puts "(Serial#: $serial, MAC Address: $mac_addr for $ip)"
        }
        timeout {
            puts "(Timeout occurred waiting for rkscli prompt on $ip.)"
            set status_msg "(Timeout occurred waiting for rkscli prompt on $ip.)"
            lappend result_list "$ip,$status_msg,$serial,$mac_addr,$user,$pass"
            return 0
        }
    }

    # Operation-specific commands
    switch $operation {
        "connect_sz" {
            puts "(Setting SZ IP to $sz for $ip.)"
            send "set scg ip $sz\r"
            expect {
                -re "OK\r\n.*rkscli" {
                    puts "(SZ IP for $ip changed to $sz.)"
                    if {[info exists ::DEBUG_LOG]} {
                        puts "Debugging: set scg ip $sz command successful: $expect_out(0,string)"
                    }
                    # Verify SZ IP
                    send "get scg\r"
                    expect {
                        -re "Server List:.*$sz.*rkscli" {
                            set status_msg "(Changed SZ IP for $ip confirmed as $sz.)"
                            puts $status_msg
                            if {[info exists ::DEBUG_LOG]} {
                                puts "Debugging: get scg output: $expect_out(0,string)"
                            }
                        }
                        timeout {
                            puts "(Timeout occurred while verifying SZ IP for $ip.)"
                            set status_msg "(Timeout occurred while verifying SZ IP for $ip.)"
                            lappend result_list "$ip,$status_msg,$serial,$mac_addr,$user,$pass"
                            return 0
                        }
                    }
                }
                timeout {
                    puts "(Timeout occurred while setting SZ IP for $ip.)"
                    set status_msg "(Timeout occurred while setting SZ IP for $ip.)"
                    lappend result_list "$ip,$status_msg,$serial,$mac_addr,$user,$pass"
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
                    set status_msg "(Reboot command executed on $ip.)"
                    puts $status_msg
                    if {[info exists ::DEBUG_LOG]} {
                        puts "Debugging: rkscli prompt received after reboot command."
                    }
                }
                timeout {
                    puts "(Timeout occurred during reboot on $ip.)"
                    set status_msg "(Timeout occurred during reboot on $ip.)"
                    lappend result_list "$ip,$status_msg,$serial,$mac_addr,$user,$pass"
                    return 0
                }
            }
        }
        "factory_reset" {
            puts "(Factory resetting $ip.)"
            set timeout 10
            send "set factory\r"
            expect {
                -re "OK\r\n.*rkscli" {
                    puts "(Factory reset command completed on $ip.)"
                    if {[info exists ::DEBUG_LOG]} {
                        puts "Debugging: set factory command successful: $expect_out(0,string)"
                    }
                }
                timeout {
                    puts "(Timeout occurred during factory reset on $ip.)"
                    set status_msg "(Timeout occurred during factory reset on $ip.)"
                    lappend result_list "$ip,$status_msg,$serial,$mac_addr,$user,$pass"
                    return 0
                }
            }
            send "reboot\r"
            expect {
                -re "rkscli" {
                    set status_msg "(Factory reset completed and reboot command executed on $ip.)"
                    puts $status_msg
                    if {[info exists ::DEBUG_LOG]} {
                        puts "Debugging: rkscli prompt received after reboot command."
                    }
                }
                timeout {
                    puts "(Timeout occurred during reboot on $ip.)"
                    set status_msg "(Timeout occurred during reboot on $ip.)"
                    lappend result_list "$ip,$status_msg,$serial,$mac_addr,$user,$pass"
                    return 0
                }
                eof {
                    puts "(Reboot command sent, connection closed for $ip.)"
                    set status_msg "(Reboot command sent, connection closed for $ip.)"
                }
            }
        }
        "changeip" {
            puts "(Changing IP address of $ip to $new_ip.)"
            set timeout 5
            send "set ipaddr wan $new_ip $subnet $gw\r"
            expect {
                -re "OK\r\n.*rkscli" {
                    set status_msg "(IP address of $ip changed to $new_ip.)"
                    puts $status_msg
                    if {[info exists ::DEBUG_LOG]} {
                        puts "Debugging: set ipaddr wan $new_ip $subnet $gw command successful: $expect_out(0,string)"
                    }
                    # Skip get ipaddr due to potential connection loss
                    puts "(Skipping IP verification after change for $ip, connection loss possible.)"
                    if {[info exists ::DEBUG_LOG]} {
                        puts "Debugging: Skipping get ipaddr, connection unreliable after IP change."
                    }
                }
                timeout {
                    puts "(Connection interrupted during IP change for $ip. Moving to next device.)"
                    set status_msg "(Connection interrupted during IP change for $ip.)"
                    lappend result_list "$ip,$status_msg,$serial,$mac_addr,$user,$pass"
                    return 0
                }
                eof {
                    puts "(IP change command sent, connection closed for $ip.)"
                    set status_msg "(IP change command sent, connection closed for $ip.)"
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
                    set status_msg "(Hostname of $ip changed to \"$hostname\".)"
                    puts $status_msg
                    if {[info exists ::DEBUG_LOG]} {
                        puts "Debugging: set device-name $hostname command successful: $expect_out(0,string)"
                    }
                    # Verify device name
                    set timeout 3
                    send "get device-name\r"
                    expect {
                        -re "device name.:.*$hostname.*rkscli|Device Name.:.*$hostname.*rkscli" {
                            puts "(Changed hostname for $ip confirmed as \"$hostname\".)"
                            if {[info exists ::DEBUG_LOG]} {
                                puts "Debugging: get device-name output: $expect_out(0,string)"
                            }
                        }
                        timeout {
                            puts "(Timeout occurred while verifying hostname setting for $ip.)"
                            set status_msg "(Timeout occurred while verifying hostname setting for $ip.)"
                            lappend result_list "$ip,$status_msg,$serial,$mac_addr,$user,$pass"
                            return 0
                        }
                    }
                }
                timeout {
                    puts "(Timeout occurred while setting hostname for $ip.)"
                    set status_msg "(Timeout occurred while setting hostname for $ip.)"
                    lappend result_list "$ip,$status_msg,$serial,$mac_addr,$user,$pass"
                    return 0
                }
            }
        }
        "sz_devicename_changeip" {
            puts "(Setting SZ IP to $sz, hostname to \"$hostname\", and IP to $new_ip for $ip.)"
            set timeout 10
            # Set SCG IP
            send "set scg ip $sz\r"
            expect {
                -re "OK\r\n.*rkscli" {
                    puts "(SZ IP set for $ip completed.)"
                    if {[info exists ::DEBUG_LOG]} {
                        puts "Debugging: set scg ip $sz command successful: $expect_out(0,string)"
                    }
                    # Verify SZ IP
                    send "get scg\r"
                    expect {
                        -re "Server List:.*$sz.*rkscli" {
                            puts "(SZ IP for $ip confirmed as $sz.)"
                            if {[info exists ::DEBUG_LOG]} {
                                puts "Debugging: get scg output: $expect_out(0,string)"
                            }
                        }
                        timeout {
                            puts "(Timeout occurred while verifying SZ IP for $ip.)"
                            set status_msg "(Timeout occurred while verifying SZ IP for $ip.)"
                            lappend result_list "$ip,$status_msg,$serial,$mac_addr,$user,$pass"
                            return 0
                        }
                    }
                }
                timeout {
                    puts "(Timeout occurred while setting SZ IP for $ip.)"
                    set status_msg "(Timeout occurred while setting SZ IP for $ip.)"
                    lappend result_list "$ip,$status_msg,$serial,$mac_addr,$user,$pass"
                    return 0
                }
            }
            # Set device name
            send "set device-name $hostname\r"
            expect {
                -re "OK\r\n.*rkscli" {
                    puts "(Hostname set for $ip completed.)"
                    if {[info exists ::DEBUG_LOG]} {
                        puts "Debugging: set device-name $hostname command successful: $expect_out(0,string)"
                    }
                    # Verify device name
                    send "get device-name\r"
                    expect {
                        -re "Device Name:.*$hostname.*rkscli" {
                            puts "(Hostname for $ip confirmed as \"$hostname\".)"
                            if {[info exists ::DEBUG_LOG]} {
                                puts "Debugging: get device-name output: $expect_out(0,string)"
                            }
                        }
                        timeout {
                            puts "(Timeout occurred while verifying hostname setting for $ip.)"
                            set status_msg "(Timeout occurred while verifying hostname setting for $ip.)"
                            lappend result_list "$ip,$status_msg,$serial,$mac_addr,$user,$pass"
                            return 0
                        }
                    }
                }
                timeout {
                    puts "(Timeout occurred while setting hostname for $ip.)"
                    set status_msg "(Timeout occurred while setting hostname for $ip.)"
                    lappend result_list "$ip,$status_msg,$serial,$mac_addr,$user,$pass"
                    return 0
                }
            }
            # Set IP address
            set timeout 5
            send "set ipaddr wan $new_ip $subnet $gw\r"
            expect {
                -re "OK\r\n.*rkscli" {
                    puts "(IP change for $ip completed.)"
                    set status_msg "(Change for $ip to SZ-$sz, Hostname-$hostname, IP-$new_ip completed.)"
                    puts $status_msg
                    if {[info exists ::DEBUG_LOG]} {
                        puts "Debugging: set ipaddr wan $new_ip $subnet $gw command successful: $expect_out(0,string)"
                    }
                    # Skip get ipaddr due to potential connection loss
                    puts "(Skipping IP verification after change for $ip, connection loss possible.)"
                    if {[info exists ::DEBUG_LOG]} {
                        puts "Debugging: Skipping get ipaddr, connection unreliable after IP change."
                    }
                }
                timeout {
                    puts "(Timeout occurred during IP change for $ip.)"
                    set status_msg "(Timeout occurred during IP change for $ip.)"
                    lappend result_list "$ip,$status_msg,$serial,$mac_addr,$user,$pass"
                    return 0
                }
                eof {
                    puts "(IP change command sent, connection closed for $ip.)"
                    set status_msg "(IP change command sent, connection closed for $ip.)"
                }
            }
            set timeout 5
        }
        default {
            puts "Unknown operation: $operation"
            set status_msg "(Unknown operation: $operation)"
            lappend result_list "$ip,$status_msg,$serial,$mac_addr,$user,$pass"
            exit 1
        }
    }

    # Append to result list if status_msg is set (success or specific failure)
    if {$status_msg != ""} {
        lappend result_list "$ip,$status_msg,$serial,$mac_addr,$user,$pass"
    }
    puts "(Moving to the next device.)"
    return 1
}

# Open CSV file
if {[catch {set fp [open $filename r]} err]} {
    puts "Error: Could not open CSV file $filename: $err"
    exit 1
}

set index 0
while {[gets $fp line] >= 0} {
    # Clean line endings (handle Windows \r\n)
    set line [string trim [regsub -all {\r\n|\r|\n} $line ""]]
    
    # Skip empty lines
    if {$line == ""} {
        if {[info exists ::DEBUG_LOG]} {
            puts "Debugging: Skipping empty line (line [expr {$index + 1}])."
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
        puts "Debugging: Line $index - Original: '$line'"
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
            puts "Debugging: Line $index - All fields are empty: '$line'"
        }
        continue
    }

    # Log fields for debugging
    if {[info exists ::DEBUG_LOG]} {
        puts "Debugging: Line $index - Fields: [join $fields "|"]"
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

    # Initialize serial and mac_addr to avoid undefined variable errors
    set serial ""
    set mac_addr ""

    # Validate IP addresses (basic check)
    if {$ip == "" || ($operation != "connect_sz" && $operation != "reboot" && $operation != "factory_reset" && $new_ip == "")} {
        puts "Error: Line $index - IP address is empty: '$line'"
        lappend result_list "$ip,(Error: Line $index - IP address is empty.),$serial,$mac_addr,$user,$pass"
        continue
    }

    # Validate SZ IP for connect_sz and sz_devicename_changeip
    if {($operation == "connect_sz" || $operation == "sz_devicename_changeip") && $sz == ""} {
        puts "Error: Line $index - SZ IP is empty: '$line'"
        lappend result_list "$ip,(Error: Line $index - SZ IP is empty.),$serial,$mac_addr,$user,$pass"
        continue
    }

    # Validate hostname for devicename and sz_devicename_changeip
    if {($operation == "devicename" || $operation == "sz_devicename_changeip") && $hostname == ""} {
        puts "Error: Line $index - hostname is empty: '$line'"
        lappend result_list "$ip,(Error: Line $index - hostname is empty.),$serial,$mac_addr,$user,$pass"
        continue
    }

    # Print processing message
    puts "Processing: $ip (Operation: $operation...)"

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
                    puts "(Password prompt timeout on $ip.)"
                    lappend result_list "$ip,(Password prompt timeout on $ip.),$serial,$mac_addr,$user,$pass"
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
            puts "(Could not connect to $ip. Moving to the next device.)"
            lappend result_list "$ip,(Could not connect to $ip.),$serial,$mac_addr,$user,$pass"
            close
            wait
            continue
        }
        "Connection closed*" {
            puts "(Connection closed by $ip. Moving to the next device.)"
            lappend result_list "$ip,(Connection closed by $ip.),$serial,$mac_addr,$user,$pass"
            close
            wait
            continue
        }
        timeout {
            puts "(Connection to $ip timed out. Moving to the next device.)"
            lappend result_list "$ip,(Connection to $ip timed out.),$serial,$mac_addr,$user,$pass"
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
                    puts "(Password prompt timeout on $ip.)"
                    lappend result_list "$ip,(Password prompt timeout on $ip.),$serial,$mac_addr,$user,$pass"
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
            puts "($ip -> This script is not applicable to Unleashed devices. Moving to the next device.)"
            lappend result_list "$ip,(This script is not applicable to Unleashed devices.),$serial,$mac_addr,$user,$pass"
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
                puts "(Error occurred while processing $ip, moving to the next device.)"
                lappend result_list "$ip,(Error occurred while processing $ip.),$serial,$mac_addr,$user,$pass"
            }
            continue
        }
        # 2) Incorrect password, try sp-admin
        -re "Login incorrect" {
            expect {
                -re "Please login" {
                    puts "(Login failed for $ip, attempting with sp-admin.)"
                    sleep 2
                    send "$user\r"
                    expect {
                        -re "password" {
                            send "sp-admin\r"
                        }
                        timeout {
                            puts "(sp-admin login prompt timeout on $ip.)"
                            lappend result_list "$ip,(sp-admin login prompt timeout on $ip.),$serial,$mac_addr,$user,sp-admin"
                            close
                            wait
                            continue
                        }
                    }
                    expect {
                        # 2-1) sp-admin correct
                        -re "rkscli" {
                            sleep 1
                            set result [process_device $ip $user "sp-admin" $new_ip $subnet $gw $sz $hostname $operation]
                            close
                            wait
                            if {$result == 0} {
                                puts "(Error occurred while processing $ip, moving to the next device.)"
                                lappend result_list "$ip,(Error occurred while processing $ip.),$serial,$mac_addr,$user,sp-admin"
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
                                    puts "(Password confirmation timeout on $ip.)"
                                    lappend result_list "$ip,(Password confirmation timeout on $ip.),$serial,$mac_addr,$user,sp-admin"
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
                                            puts "(New password login prompt timeout on $ip.)"
                                            lappend result_list "$ip,(New password login prompt timeout on $ip.),$serial,$mac_addr,$user,ruckus12#$"
                                            close
                                            wait
                                            continue
                                        }
                                    }
                                    expect {
                                        -re "rkscli" {
                                            sleep 1
                                            set result [process_device $ip $user "ruckus12#$" $new_ip $subnet $gw $sz $hostname $operation]
                                            close
                                            wait
                                            if {$result == 0} {
                                                puts "(Error occurred while processing $ip, moving to the next device.)"
                                                lappend result_list "$ip,(Error occurred while processing $ip.),$serial,$mac_addr,$user,ruckus12#$"
                                            }
                                            continue
                                        }
                                        timeout {
                                            puts "(rkscli prompt timeout on $ip.)"
                                            lappend result_list "$ip,(rkscli prompt timeout on $ip.),$serial,$mac_addr,$user,ruckus12#$"
                                            close
                                            wait
                                            continue
                                        }
                                    }
                                }
                                timeout {
                                    puts "(New login prompt timeout on $ip.)"
                                    lappend result_list "$ip,(New login prompt timeout on $ip.),$serial,$mac_addr,$user,sp-admin"
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
                                    puts "(Login failed for $ip, attempting with ruckus12#$.)"
                                    sleep 2
                                    send "$user\r"
                                    expect {
                                        -re "password" {
                                            send "ruckus12#$\r"
                                        }
                                        timeout {
                                            puts "(ruckus12#$ login prompt timeout on $ip.)"
                                            lappend result_list "$ip,(ruckus12#$ login prompt timeout on $ip.),$serial,$mac_addr,$user,ruckus12#$"
                                            close
                                            wait
                                            continue
                                        }
                                    }
                                    expect {
                                        # 2-3-1) ruckus12#$ correct
                                        -re "rkscli" {
                                            sleep 1
                                            set result [process_device $ip $user "ruckus12#$" $new_ip $subnet $gw $sz $hostname $operation]
                                            close
                                            wait
                                            if {$result == 0} {
                                                puts "(Error occurred while processing $ip, moving to the next device.)"
                                                lappend result_list "$ip,(Error occurred while processing $ip.),$serial,$mac_addr,$user,ruckus12#$"
                                            }
                                            continue
                                        }
                                        # 2-3-2) ruckus12#$ incorrect
                                        -re "Login incorrect" {
                                            puts "(Login failed repeatedly for $ip, moving to the next device.)"
                                            lappend result_list "$ip,(Login failed repeatedly.),$serial,$mac_addr,$user,ruckus12#$"
                                            close
                                            wait
                                            continue
                                        }
                                        # 2-3-3) ruckus12#$ with unleashed
                                        -re "(ruckus>|ruckus#)" {
                                            puts "($ip -> This script is not applicable to Unleashed devices. Moving to the next device.)"
                                            lappend result_list "$ip,(This script is not applicable to Unleashed devices.),$serial,$mac_addr,$user,ruckus12#$"
                                            close
                                            wait
                                            continue
                                        }
                                        timeout {
                                            puts "(Timeout after ruckus12#$ login on $ip.)"
                                            lappend result_list "$ip,(Timeout after ruckus12#$ login on $ip.),$serial,$mac_addr,$user,ruckus12#$"
                                            close
                                            wait
                                            continue
                                        }
                                    }
                                }
                                timeout {
                                    puts "(Timeout after sp-admin login on $ip.)"
                                    lappend result_list "$ip,(Timeout after sp-admin login on $ip.),$serial,$mac_addr,$user,sp-admin"
                                    close
                                    wait
                                    continue
                                }
                            }
                        }
                        # 2-4) sp-admin with unleashed
                        -re "(ruckus>|ruckus#)" {
                            puts "($ip -> This script is not applicable to Unleashed devices. Moving to the next device.)"
                            lappend result_list "$ip,(This script is not applicable to Unleashed devices.),$serial,$mac_addr,$user,sp-admin"
                            close
                            wait
                            continue
                        }
                        timeout {
                            puts "(Timeout during sp-admin login process on $ip.)"
                            lappend result_list "$ip,(Timeout during sp-admin login process on $ip.),$serial,$mac_addr,$user,sp-admin"
                            close
                            wait
                            continue
                        }
                    }
                }
                timeout {
                    puts "(Login prompt timeout on $ip.)"
                    lappend result_list "$ip,(Login prompt timeout on $ip.),$serial,$mac_addr,$user,$pass"
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
                    puts "(Password confirmation timeout on $ip.)"
                    lappend result_list "$ip,(Password confirmation timeout on $ip.),$serial,$mac_addr,$user,ruckus12#$"
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
                            puts "(New password login prompt timeout on $ip.)"
                            lappend result_list "$ip,(New password login prompt timeout on $ip.),$serial,$mac_addr,$user,ruckus12#$"
                            close
                            wait
                            continue
                        }
                    }
                    expect {
                        -re "rkscli" {
                            sleep 1
                            set result [process_device $ip $user "ruckus12#$" $new_ip $subnet $gw $sz $hostname $operation]
                            close
                            wait
                            if {$result == 0} {
                                puts "(Error occurred while processing $ip, moving to the next device.)"
                                lappend result_list "$ip,(Error occurred while processing $ip.),$serial,$mac_addr,$user,ruckus12#$"
                            }
                            continue
                        }
                        timeout {
                            puts "(rkscli prompt timeout on $ip.)"
                            lappend result_list "$ip,(rkscli prompt timeout on $ip.),$serial,$mac_addr,$user,ruckus12#$"
                            close
                            wait
                            continue
                        }
                    }
                }
                timeout {
                    puts "(New login prompt timeout on $ip.)"
                    lappend result_list "$ip,(New login prompt timeout on $ip.),$serial,$mac_addr,$user,ruckus12#$"
                    close
                    wait
                    continue
                }
            }
        }
        timeout {
            puts "(Timeout occurred during login process on $ip.)"
            lappend result_list "$ip,(Timeout occurred during login process on $ip.),$serial,$mac_addr,$user,$pass"
            close
            wait
            continue
        }
    }
}

close $fp

# Write results to CSV with UTF-8 BOM
if {[catch {set result_fp [open $result_file w]} err]} {
    puts "Error: Could not open result file $result_file: $err"
    exit 1
}

# 1. Explicitly set file channel encoding to UTF-8 (Most important) - Comment out on centos, Rocky.
#fconfigure $result_fp -encoding utf-8

# 2. Write the BOM character \uFEFF. (This is key to prevent Korean characters from breaking in Excel.) - Use \xEF\xBB\xBF on centos, Rocky.
#    The puts -nonewline command must be used so the BOM character is at the very start of the file without a newline.
#puts -nonewline $result_fp "\uFEFF"
puts -nonewline $result_fp "\xEF\xBB\xBF"

# 3. Write the CSV header.
#    The Status (result message) field is added as the second column to the original header.
puts $result_fp "IP,Status,Serial,MAC_Address,User,Pass"

# 4. Write the result list
foreach result $result_list {
    puts $result_fp $result
}
close $result_fp

puts "\n※ Script finished.\n"
