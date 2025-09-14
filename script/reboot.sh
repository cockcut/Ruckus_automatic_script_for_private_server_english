#!/usr/bin/expect -f
#디버깅 시 exp_internal 1
# 디버깅시 주석 제거
#exp_internal 1
# 디버깅시 주석 제거
#log_file reboot_debug.log
# 모든 명령어 전 대기 시간
set timeout 5

#로그파일 초기화
file delete "reboot.log"

set result_list [list]

# PHP에서 업로드된 CSV 파일 경로 받기
set filename [lindex $argv 0]

# Serial과 MAC 주소 추출 및 IP 변경을 처리하는 프로시저
proc process_device {ip user pass new_ip subnet gw sz hostname} {
	global result_list  ;# 전역 변수로 선언
    set mac_addr ""
    set serial ""
    
    # Serial과 MAC 주소 추출
    send "get boarddata\r"
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
    puts "($ip의 Serial#: $serial, MAC 주소: $mac_addr)"
    
    # IP 변경
    puts "($ip를 재부팅합니다.)"
	expect -re "rkscli"
    send "reboot\r"
	expect -re "rkscli"
    
    # 결과를 리스트에 추가
    lappend result_list "$new_ip,$subnet,$gw,$sz,$hostname,$serial,$mac_addr,$ip,$user,$pass"
    puts "($ip를 재부팅합니다. 다음 장비로 이동합니다.)"
}

# 파일 열기
set fp [open $filename r]
set index 0

while {[gets $fp line] != -1} {
    if {$index == 0} {
        # 첫 줄(헤더) 제외
        incr index
        continue
    }
    incr index

    # 공백으로 필드 분리
    set fields [split $line ","]
    
    if {[llength $fields] != 8} {
        puts "잘못된 형식: $line"
        continue
    }
	# 변수 지정
    set ip [lindex $fields 0]
    set user [lindex $fields 1]
    set pass [lindex $fields 2]
    set new_ip [lindex $fields 3]
    set subnet [lindex $fields 4]
    set gw [lindex $fields 5]
    set sz [lindex $fields 6]
    set hostname [lindex $fields 7]
	
	# 메세지출력
    puts "처리 중: $ip 재부팅중....."

    # SSH 접속
    spawn ssh -legacy -t -o StrictHostKeyChecking=no -o HostKeyAlgorithms=+ssh-rsa -o ForwardX11=no $user@$ip
    sleep 2
	
	# 로그인 시도
    expect {
			"ssh: connect to host" {
			puts "($ip 에 접속할 수 없습니다. 다음 장비로 이동합니다.)"
			close
			wait
			continue
			}
			-re "timeout" {
			puts "($ip 접속 시간이 초과되었습니다. 다음 장비로 이동합니다.)"
			close
			wait
			continue
			}
			-re "Please login" {
				send "$user\r"
			}
    }
    	
    # 패스워드 입력
    expect -re "password"
	send "$pass\r"
	#경우의 수 시작
	expect {
	# 0) 언리시드일때
			-re "yes/no|ruckus>|ruckus#" {
				puts "($ip -> 언리시드는 이 스크립트가 젹옹되지 않습니다. 다음 장비로 이동합니다.)"
				close
				wait
				continue
			}
	# 1) 입력한 패스워드가 맞을 때
            -re "rkscli" {
				sleep 1
				process_device $ip $user $pass $new_ip $subnet $gw $sz $hostname
                continue
            }
	# 2) 입력한 패스워드가 틀리면
			#2-1) sp-admin로 시도
			-re "Login incorrect" {
				expect -re "Please login"
				puts "($ip 로그인이 실패하여 sp-admin으로 시도합니다.)"
				sleep 2
				send "$user\r"
				sleep 2
				expect -re "password"
				send "sp-admin\r"
				#2-1-1) sp-admin이 맞으면
				expect {
						-re "rkscli" {
							sleep 1
							process_device $ip $user $pass $new_ip $subnet $gw $sz $hostname
							continue
						}
				# 2-1-2) sp-admin로 시도하였는데 Newpassword가 나오면 초기화된 AP이므로 sp-admin -> ruckus12#$ 강제 적용
						-re "New password" {
							send "ruckus12#$\r"
							expect -re "Confirm password"
							send "ruckus12#$\r"
							# Confirm password 후에 Please login을 다시 요청할 수 있음
							expect -re "Please login"
							send "$user\r"
							expect -re "password"
							#패스워드 ruckus12#$ 강제 적용
							send "ruckus12#$\r"
							# Confirm password 후에 rkscli가 올 때까지 기다림
							expect -re "rkscli"
							sleep 1
							process_device $ip $user $pass $new_ip $subnet $gw $sz $hostname
							continue
						}
				# 2-1-3) sp-admin로 시도하였는데 그래도 틀리면 ruckus12#$로 시도
						-re "Login incorrect" {
							expect -re "Please login"
							puts "($ip 로그인이 실패하여 ruckus12#$로 시도합니다.)"
							sleep 2
							send "$user\r"
							sleep 2
							expect -re "password"
							send "ruckus12#$\r"
							# 2-1-3-1) ruckus12#$ 가 맞으면
							expect {
									-re "rkscli" {
										sleep 1
										process_device $ip $user $pass $new_ip $subnet $gw $sz $hostname
										continue
									}
							# 2-1-3-2) ruckus12#$ 시도했는데 그래도 실패하면 다음 장비로 이동
									-re "Login incorrect\r\n\r\nPlease login:" {
										puts "($ip 로그인이 연속으로 실패하여 다음 장비로 이동합니다.)"
										close
										wait
										continue
									}
							# 2-1-3-3) ruckus12#$ 시도했는데 언리시드일때
									-re "yes/no|ruckus>|ruckus#" {
										puts "($ip -> 언리시드는 이 스크립트가 젹옹되지 않습니다. 다음 장비로 이동합니다.)"
										close
										wait
										continue
									}
							}
						}
				#2-1-4) sp-admin 패스워드가 맞는데 언리시드일떄
						-re "yes/no|ruckus>|ruckus#" {
							puts "($ip -> 언리시드는 이 스크립트가 젹옹되지 않습니다. 다음 장비로 이동합니다.)"
							close
							wait
							continue
						}
				}
			}
	# 3) 입력한 패스워드시도후 Newpassword가 나오면 초기화된 AP이므로 sp-admin -> ruckus12#$ 강제 적용
            -re "New password" {
                send "ruckus12#$\r"
                expect -re "Confirm password"
                send "ruckus12#$\r"
                # Confirm password 후에 Please login을 다시 요청할 수 있음
                expect -re "Please login"
                send "$user\r"
                expect -re "password"
                send "ruckus12#$\r"
                # Confirm password 후에 rkscli가 올 때까지 기다림
                expect -re "rkscli"
				sleep 1
				process_device $ip $user $pass $new_ip $subnet $gw $sz $hostname
				continue
            }
	}
}

close $fp

set result_fp [open "reboot_result.csv" w]
puts $result_fp "static_IP,Subnet,GW,SZ,Hostname,Serial,MAC_Address,temp_dhcp_IP,User,Pass"
foreach result $result_list {
    puts $result_fp $result
}
close $result_fp

puts "\n※ 스크립트가 끝났습니다.\n"