#!/bin/bash

# ouiget.sh 스크립트의 절대 경로를 동적으로 가져와 변수에 저장합니다.
SCRIPT_DIR="$(dirname "$(readlink -f "$0")")"

# 먼저 기존에 남아있는 OUI파일을 삭제합니다.
rm -f "$SCRIPT_DIR/oui/index.html"*

# OUI를 디렉토리에 저장합니다.
wget https://standards-oui.ieee.org/ -P "$SCRIPT_DIR/oui/"

# OUI 파일의 내용을 읽고, "(hex)"가 포함된 줄을 필터링합니다.
cat "$SCRIPT_DIR/oui/index.html" | \
grep hex | \

# "파싱을 용이하게 하기 위해 (hex)"를 구분자 "|"로 변경합니다.
sed 's/(hex)/|/g' | \

# awk를 사용하여 구분자 "|"로  열을 구분하고 데이터 형식을 지정합니다.
awk -F '|' -v timestamp="$(date +"%Y/%m/%d/%H:%M")" '

# 시작 시, 헤더와 타임스탬프를 출력합니다.
  BEGIN {
    print "*" timestamp " 업데이트\n"
#    print "OUI\t\tOUI(:)\t\tOUI(lower)\t\tOUI(:lower)\t\t\t\tVender"
    print "OUI\t\tOUI(:)\t\tOUI(lower)\tOUI(:lower)\t\t\tVender"
    print "------------------------------------------------------------------------------------------------------------------------------------------"
  }

# 각 줄을 처리합니다.
  {
# 원본 OUI 값을 변수에 저장합니다.
    original_oui = $1
# 콜론으로 변경된 OUI 값을 생성합니다.
    colon_oui = original_oui
    gsub("-", ":", colon_oui)
# 소문자로 변경된 OUI 값을 생성합니다.
    lower_oui = tolower(original_oui)
# 콜론으로 변경된 OUI 값을 소문자로 변환합니다.
    lower_colon_oui = tolower(colon_oui)
    
# 총 5개의 열을 출력합니다.
#    printf "%s\t\t%s\t\t%s\t\t%s\t\t%s\n", original_oui, colon_oui, lower_oui, lower_colon_oui, $2
    printf "%s\t%s\t%s\t%s\t%s\n", original_oui, colon_oui, lower_oui, lower_colon_oui, $2
  }
# 끝날 때, 푸터를 출력합니다.
  END {
    print "------------------------------------------------------------------------------------------------------------------------------------------\nEND"
  }
' > $SCRIPT_DIR/oui/oui.txt
rm -f $SCRIPT_DIR/oui/index.html*

