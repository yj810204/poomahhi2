<?php
    /**
     * @file   ko.lang.php
     * @author CONORY (https://xe.conory.com)
     * @brief  포인트 히스토리(pointhistory) 모듈의 기본 언어팩
     **/
	
	$lang->pointhistory = '포인트 히스토리';
	$lang->about_pointhistory = '포인트 사용 기록을 수집하여 여러가지 포인트 통계를 제공해주는 모듈입니다.';
	$lang->msg_not_exist_data = '데이터가 존재하지 않습니다.';
	$lang->preferences = '환경설정';
	$lang->member_point_history_list = '회원 포인트 내역';
	$lang->point_history_list = '포인트 내역';
	$lang->history_type = '구분';
	$lang->accumulate = '적립';
	$lang->add_member_menu = '회원 메뉴 추가';
	$lang->about_history_add_member_menu = '포인트 내역을 회원 메뉴에 추가하여 볼 수 있게 합니다.';
	$lang->member_menu_name = '회원 메뉴 이름';
	$lang->about_member_menu_name = '회원 메뉴에 추가된 포인트 내역의 메뉴 이름입니다.';
	$lang->point_unit_char = '포인트 단위 문자';
	$lang->about_point_unit_char = '포인트 마지막에 붙는 단위 문자입니다.';
	$lang->delete_record_auto = '기록 자동 삭제';
	$lang->about_history_delete_record_auto = '지정 일자가 지난 히스토리 기록을 자동으로 삭제합니다. (0이면 자동 삭제하지않음)<br>히스토리 기록이 많이 쌓여있을 경우 DB에 부담이 되며, 주기적으로 삭제해주는 것이 좋습니다.';
	$lang->delete_all_record = '모든 기록 삭제';
	$lang->delete_record_leave = '탈퇴시 기록 삭제';
	$lang->about_history_delete_record_leave = '탈퇴시 해당 회원의 히스토리, 일별 현황 기록도 함께 삭제합니다.';
	$lang->increase_name = '\'증가\' 이름';
	$lang->decrease_name = '\'감소\' 이름';
	$lang->about_history_increase_name = '포인트가 증가될때 붙는 구분자 이름입니다.';
	$lang->about_history_decrease_name = '포인트가 감소될때 붙는 구분자 이름입니다.';
	$lang->point_status = '포인트 현황';
	$lang->total_point_status = '전체 현황';
	$lang->member_point_status = '회원별 현황';
	$lang->day_fluctuation = '당일 변동';
	$lang->last_fluctuation_time = '마지막 변동시간';
	$lang->total_point = '전체 포인트';
	$lang->point_status_except_admin = '관리자 기록 제외';
	$lang->about_point_status_except_admin = '포인트 현황 기록시 관리자는 제외하여 기록하지 않습니다.';
	$lang->point_status_use = '포인트 현황 사용';
	$lang->about_point_status = '포인트 기록과 더불어 일별 포인트를 따로 기록합니다. 하루에 포인트가 얼마나 사용되는 지 흐름을 한눈에 알 수 있습니다.<br>특히 포인트 관련 프로그램에서 현황 기록을 유용하게 활용할 수 있습니다.';
	$lang->about_point_status_delete_record_auto = '지정 일자가 지난 포인트 현황 기록을 자동으로 삭제합니다. (0이면 자동 삭제하지않음)';
	
	// Upgrade
	$lang->upgrade_start = '업그레이드 시작 (동의함)';
	$lang->confirm_upgrade_start = '업그레이드를 시작하면 사이트 접속 속도에 영향을 줄 수 있습니다.';
	$lang->success_upgrade = '업그레이드 작업이 완료되었습니다.';
	$lang->failure_upgrade = '업그레이드에 실패하였습니다. 재시작하면 실패한 부분부터 이어서 시작됩니다.';
	$lang->pointhistory_upgrading = '업그레이드 작업중 입니다.';
	$lang->processing_speed = '처리 속도';
	$lang->processing_speed1 = '저속 (저사양 서버)';
	$lang->processing_speed2 = '보통 (웹호스팅 서버)';
	$lang->processing_speed3 = '고속 (단독 서버)';
	$lang->processing_speed4 = '초고속 (고사양 서버)';
	$lang->about_processing_speed = '처리 속도가 높을 수록 서버성능에 따라 빨라질 수 있지만, 접속이 불안정해질 수 있으며 실패할 확률도 높아집니디.';
	$lang->msg_not_processing_speed = '로그아웃 전까지 처리 속도를 변경할 수 없습니다.';
	$lang->about_pointhistory_upgrading = '데이터량에 따라 수 초 ~ 몇 십분까지 걸릴 수 있습니다.<br>업그레이드 도중에 로그아웃 되면 처음부터 다시 시작됩니다.';
	$lang->upgrade_use_new = '새로 사용하기 (기존 데이터 삭제)';
	$lang->success_upgrade_use_new = '환영합니다!';
	$lang->confirm_upgrade_use_new = '기존의 데이터(포인트 기록)가 모두 삭제됩니다.';
	$lang->pointhistory_upgrade = 'Upgrade (히스토리 기록 이전)';
	$lang->pointhistory_upgrade_title = '포인트 히스토리 모듈을 계속 사용하려면 업그레이드 작업이 필요합니다.';
	$lang->about_pointhistory_upgrade = '본 업그레이드는 기존의 히스토리 데이터를 새로운 DB 테이블로 이전하는 작업입니다.<br>만약 [새로 사용하기] 버튼을 누르면 이전 작업 없이 기존의 데이터(포인트 기록)가 모두 삭제되며 새로 설치한 것 처럼 사용할 수 있습니다.';
	$lang->about_pointhistory_upgrade_time = '이 작업은 데이터량에 따라 수 초 ~ 몇 십분까지 걸릴 수 있습니다.<br>업그레이드에 실패할 경우 재시작하면 실패한 부분부터 이어서 시작됩니다. (단, 중간에 로그아웃되면 안됩니다.)';
	$lang->about_warning_pointhistory_upgrade = '업그레이드 작업은 사이트 접속 속도에 영향을 줄 수 있으니, <b>이용자가 적은 시간대에</b> 실행하시기바랍니다.';
	$lang->msg_need_pointhistory_upgrade = '별도의 업그레이드 작업이 필요합니다.<br>포인트 히스토리 관리 페이지에서 업그레이드를 실행해주세요.';
	$lang->msg_pointhistory_overlay_directory = '포인트 히스토리 모듈 구버전 폴더에 업데이트 버전을 덮어씌우면 정상적으로 작동하지 않을 수 있습니다. <br><br>구버전 폴더는 삭제하고 새로 설치해주세요.';
	$lang->msg_pointhistory_no_new_table = '새로운 테이블이 생성되어있지 않습니다.<br>관리자 메인에서 [DB Table 생성하기] 버튼을 눌러주세요.';
	$lang->msg_pointhistory_upgrading = '진행바가 한참동안 움직이지 않는다면 새로고침후 재시작해주세요.';
	
	// 포인트 기본 메세지
	$lang->point_message_member_login = '금일 첫 로그인';
	$lang->point_message_member_login_auto = '금일 첫 로그인 (자동로그인)';
	$lang->point_message_member_join = '회원가입 기념';
	$lang->point_message_member_join_sns = 'SNS 첫 로그인 포인트';
	$lang->point_message_insert_document = '%s에서 게시글 작성 <b>( %s )</b>';
	$lang->point_message_delete_document = '%s에서 게시글 삭제로 회수';
	$lang->point_message_insert_comment = '%s에서 댓글 작성';
	$lang->point_message_delete_comment = '%s에서 댓글 삭제로 회수';
	$lang->point_message_read_document = '%s에서 게시글 열람 ( <a href = "%s">%s</a> )';
	$lang->point_message_update_admin = '관리자에 의한 포인트 변동';
	$lang->point_message_download_file = '%s에서 첨부파일 다운로드 ( <a href = "%s">%s</a> )';
	$lang->point_message_delete_file = '첨부파일 삭제로 회수';
	$lang->point_message_document_vote_up = '%s에 올린 게시글이 추천받음 ( <a href = "%s">%s</a> )';
	$lang->point_message_document_vote_down = '%s에 올린 게시글이 비추천받음 ( <a href = "%s">%s</a> )';
	$lang->point_message_document_manage = '관리자의 게시물 관리에 의한 포인트 변동';
	$lang->point_message_unknown = '알 수 없음';
	$lang->point_message_content_minus = '%s에서 %s';
	$lang->point_message_content_add = '%s에서 %s';