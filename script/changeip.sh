#!/usr/bin/expect -f
# For debugging, use exp_internal 1
# Uncomment for debugging
#exp_internal 1
# Uncomment for debugging
#log_file changeip_debug.log
# Wait time before all commands
set timeout 5

# Initialize log file
file delete "changeip.log"

set result_list [list]

# Get the path of the CSV file uploaded from PHP
set filename [lindex $argv 0]

# Procedure to extract Serial and MAC addresses and handle IP change
proc process_device {ip user pass new_ip subnet gw sz hostname} {
	global result_list  ;# Declare as a global variable
	set mac_addr ""
	set serial ""

	# Extract Serial and MAC address
	puts "rkscli: get boarddata"
	send "get boarddata\r"
	send "\r"
	send "\r"
	sleep 1
	expect {
			-re {Serial#:\s+([0-9:]+).*base\s([0-9A-Fa-f:]+)} {
				set serial $expect_out(1,string)
				set mac_addr $expect_out(2,string)
				puts "Serial: $serial"
				puts "mac_addr: $mac_addr"
			}
	}
	send "\r"
	expect -re "rkscli"
	puts "(Serial number for $ip: $serial, MAC address: $mac_addr)"
	
    # Change Hostname
    puts "(Changing hostname of $ip to \"$hostname\".)"
	expect -re "rkscli"
	puts "rkscli: set device-name $hostname"
    send "set device-name $hostname\r"
	expect -re "rkscli"
	
	# Set SZ IP
    puts "(Setting SZ IP of $ip to $sz.)"
    send "set scg ip $sz\r"
	expect -re "rkscli"
	
	# Change IP
	puts "(Changing $ip to $new_ip.)"
	expect -re "rkscli"
	puts "rkscli: set ipaddr wan $new_ip $subnet $gw"
	expect -re "rkscli"
	send "set ipaddr wan $new_ip $subnet $gw\r"
	expect -re "rkscli"

	# Add result to list
	lappend result_list "$new_ip,$subnet,$gw,$sz,$hostname,$serial,$mac_addr,$ip,$user,$pass"
	puts "(The hostname of $ip has been changed to $hostname. Moving to the next device.)"
	puts "(The SZ IP of $ip has been set to $sz. Moving to the next device.)"
	puts "(The IP of $ip has been changed to $new_ip. Moving to the next device.)"

}

# Open file
set fp [open $filename r]
set index 0

while {[gets $fp line] != -1} {
    if {$index == 0} {
        # Exclude the first line (header)
        incr index
        continue
    }
    incr index

    # Separate fields by comma
    set fields [split $line ","]
    
    if {[llength $fields] != 8} {
        puts "Incorrect format: $line"
        continue
    }
	# Assign variables
    set ip [lindex $fields 0]
    set user [lindex $fields 1]
    set pass [lindex $fields 2]
    set new_ip [lindex $fields 3]
    set subnet [lindex $fields 4]
    set gw [lindex $fields 5]
    set sz [lindex $fields 6]
    set hostname [lindex $fields 7]
	
	# Print message
    puts "Processing: Changing $ip to $new_ip..."

    # SSH connection
    spawn ssh -legacy -t -o StrictHostKeyChecking=no -o HostKeyAlgorithms=+ssh-rsa -o ForwardX11=no $user@$ip
    sleep 2
	
	# Login attempt
    expect {
			"ssh: connect to host" {
			puts "(Cannot connect to $ip. Moving to the next device.)"
			close
			wait
			continue
			}
			timeout {
				puts "($ip connection timed out. Moving to the next device.)"
				close
				wait
				continue
			}
			-re "timeout" {
				puts "($ip connection timed out. Moving to the next device.)"
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
	# Start of cases
	expect {
	# 0) When it is Unleashed
			-re "yes/no|ruckus>|ruckus#" {
				puts "($ip -> This script does not apply to Unleashed. Moving to the next device.)"
				close
				wait
                continue
            }
	# 1) When the entered password is correct
            -re "rkscli" {
				sleep 1
				process_device $ip $user $pass $new_ip $subnet $gw $sz $hostname
                continue
            }
	# 2) If the entered password is wrong
			#2-1) Attempt with sp-admin
			-re "Login incorrect" {
				expect -re "Please login"
				puts "($ip login failed, attempting with sp-admin.)"
				sleep 2
				send "$user\r"
				sleep 2
				expect -re "password"
				send "sp-admin\r"
				#2-1-1) If sp-admin is correct
				expect {
						-re "rkscli" {
							sleep 1
							process_device $ip $user $pass $new_ip $subnet $gw $sz $hostname
							continue
						}
				# 2-1-2) If New password appears after attempting with sp-admin, the AP is reset, so force sp-admin -> ruckus12#$
						-re "New password" {
							send "ruckus12#$\r"
							expect -re "Confirm password"
							send "ruckus12#$\r"
							# Please login can be requested again after Confirm password
							expect -re "Please login"
							send "$user\r"
							expect -re "password"
							# Force password ruckus12#$
							send "ruckus12#$\r"
							# Wait until rkscli appears after Confirm password
							expect -re "rkscli"
							sleep 1
							process_device $ip $user $pass $new_ip $subnet $gw $sz $hostname
							continue
						}
				# 2-1-3) If sp-admin attempt also fails, attempt with ruckus12#$
						-re "Login incorrect" {
							expect -re "Please login"
							puts "($ip login failed, attempting with ruckus12#$.)"
							sleep 2
							send "$user\r"
							sleep 2
							expect -re "password"
							send "ruckus12#$\r"
							# 2-1-3-1) If ruckus12#$ is correct
							expect {
									-re "rkscli" {
										sleep 1
										process_device $ip $user $pass $new_ip $subnet $gw $sz $hostname
										continue
									}
							# 2-1-3-2) If ruckus12#$ attempt also fails, move to the next device
									-re "Login incorrect\r\n\r\nPlease login:" {
										puts "(Continuous login failure for $ip, moving to the next device.)"
										close
										wait
										continue
									}
							# 2-1-3-3) If ruckus12#$ attempt results in Unleashed
									-re "yes/no|ruckus>|ruckus#" {
										puts "($ip -> This script does not apply to Unleashed. Moving to the next device.)"
										close
										wait
										continue
									}
							}
						}
				#2-1-4) When sp-admin password is correct but it is Unleashed
						-re "yes/no|ruckus>|ruckus#" {
							puts "($ip -> This script does not apply to Unleashed. Moving to the next device.)"
							close
							wait
							continue
						}
				}
			}
	# 3) If New password appears after attempting with the entered password, the AP is reset, so force sp-admin -> ruckus12#$
			-re "New password" {
                send "ruckus12#$\r"
                expect -re "Confirm password"
                send "ruckus12#$\r"
                # Please login can be requested again after Confirm password
                expect -re "Please login"
                send "$user\r"
                expect -re "password"
                send "ruckus12#$\r"
                # Wait until rkscli appears after Confirm password
                expect -re "rkscli"
				sleep 1
				process_device $ip $user $pass $new_ip $subnet $gw $sz $hostname
				continue
            }
	}
}


close $fp

set result_fp [open "changeip_result.csv" w]
puts $result_fp "static_IP,Subnet,GW,SZ,Hostname,Serial,MAC_Address,temp_dhcp_IP,User,Pass"
foreach result $result_list {
    puts $result_fp $result
}
close $result_fp

puts "\n※ Script finished.\n"