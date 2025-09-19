#!/usr/bin/expect -f
# During debugging, set exp_internal 0
# Remove comments during debugging
#exp_internal 1
# Remove comments during debugging
#log_file ufw_debug.log

# Terminal environment settings
#set env(TERM) dumb
# Wait time before all commands
set timeout 5

# Get CSV file path uploaded from PHP
set filename [lindex $argv 0]
set fwname [lindex $argv 1]

# Define firmware upgrade logic as a separate procedure
proc upgrade_ap {ip model version fw_server_ip fwname} {
    set full_path [exec pwd]
    set fw_path [string trimleft $full_path "/var/www/html/"]
	puts "Upgrading AP $ip ($model) from version $version"
	send "fw set proto HTTP\r"
	expect "OK"
	send "fw set port 80\r"
	expect "OK"
	send "fw set host $fw_server_ip\r"
	expect "OK"
	send "fw set control $fw_path/$fwname\r"
	expect "OK"
	send "fw update\r"
	set timeout 180
	expect {
		-re {fw\([0-9]+\) : Completed} {
			puts "$ip upgrade complete, initializing and rebooting the AP."
			send "\r"
			sleep 1
			send "set factory\r"
			expect "OK"
			sleep 2
			send "reboot\r"
			expect "OK"
			puts "$ip reboot."
			return 1
		}
		-re "needs a reboot|Unable to get active image header" {
			puts "\033[31m$ip Upgrade failed. Initializing and rebooting the AP. Please try the upgrade again.\033[0m"
			sleep 1			
			send "set factory\r"
			expect "OK"
			sleep 2
			send "reboot\r"
			expect "OK"
			puts "$ip Rebooting the AP. Please try the upgrade again."
			return 1			
		}
	}
}

# open a file
set fp [open $filename r]
set index 0

while {[gets $fp line] != -1} {
    if {$index == 0} {
        # Exclude the first line (header)
        incr index
        continue
    }
    incr index

    # Split fields by space
    set fields [split $line ","]
    
    if {[llength $fields] != 4} {
        puts "잘못된 형식: $line"
        continue
    }
	# Variable assignment
    set ip [lindex $fields 0]
    set user [lindex $fields 1]
    set pass [lindex $fields 2]
	set fw_server_ip [lindex $fields 3]
	
	# Message output
    puts "처리 중: $ip의 펌웨어를 언리시드 펌웨어로 변경하는중..."

    # SSH connection
    spawn ssh -legacy -t -o StrictHostKeyChecking=no -o HostKeyAlgorithms=+ssh-rsa -o ForwardX11=no $user@$ip
    sleep 2
	
	# Login attempt
    expect {
        "ssh: connect to host" {
            puts "(Cannot connect to $ip. Moving to the next device.)"
            continue
        }
        timeout {
            puts "($ip connection timed out. Moving to the next device.)"
            continue
        }
        -re "Please login" {
            send "$user\r"
        }
    }
    	
    # Enter password
    expect -re "password"
	send "$pass\r"
	#Handle different scenarios
	expect {
	#0) If Unleashed
			#0-0) If operating as Unleashed
			-re "ruckus>" {
				send "enable\r"
				expect {
						# 0-0-1) If force is required after enable
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
						# 0-0-2) When entering enable mode directly
						-re "ruckus#" {  ;# When ruckus# appears immediately after enable
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
			#0-1) When it is in an initialized state with Unleashed firmware
			-re "yes/no" {  
				send "no\r"
				expect -re "ruckus>"
				send "enable\r"
				expect {
						# 0-1-1) When force is needed after enable
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
						# 0-1-2) When entering enable mode directly
						-re "ruckus#" {  ;# When ruckus# appears immediately after enable
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

	# 1) When the entered password is correct
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
	# 2) If the entered password is incorrect	
			#2-1) Try with sp-admin
			-re "Login incorrect" {
				expect -re "Please login"
				puts "($ip Login failed, attempting with sp-admin.)"
				sleep 2
				send "$user\r"
				sleep 2
				expect -re "password"
				send "sp-admin\r"
				expect {
				#2-1-1) If the entered password is correct
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
				# 2-1-2) If Newpassword appears after trying with sp-admin, it's an initialized AP, so force-apply sp-admin -> ruckus12#$.
					-re "New password" {
						send "ruckus12#$\r"
						expect -re "Confirm password"
						send "ruckus12#$\r"
						# After Confirm password, Please login may be requested again
						expect -re "Please login"
						send "super\r"
						expect -re "password"
						# Force apply password ruckus12#$
						send "ruckus12#$\r"
						# After Confirm password, wait until rkscli appears.
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
				# 2-1-3) If trying with sp-admin still fails, try with ruckus12#$
					-re "Login incorrect" {
						expect -re "Please login"
						puts "($ip Login failed, attempting with sp-admin.)"
						sleep 2
						send "$user\r"
						sleep 2
						expect -re "password"
						send "ruckus12#$\r"
						# 2-1-3-1) If the entered password is correct
						expect {
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
						# 2-1-3-2) If trying with ruckus12#$ still fails, move to the next device
								-re "Login incorrect\r\n\r\nPlease login:" {
									puts "($ip Login failed consecutively, moving to the next device.)"
									continue
								}
						}
					}
				}
			}
			# 3) If Newpassword appears after trying with sp-admin, it's an initialized AP, so force-apply sp-admin -> ruckus12#$.
            -re "New password" {
                send "ruckus12#$\r"
                expect -re "Confirm password"
                send "ruckus12#$\r"
                # After "Confirm password", "Please login" may be requested again
                expect -re "Please login"
                send "$user\r"
                expect -re "password"
                send "ruckus12#$\r"
                # After Confirm password, wait until rkscli appears.
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

puts "\n※ Script finished.\n"






