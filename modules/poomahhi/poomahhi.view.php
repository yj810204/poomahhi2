<?php
/**
 * @class poomahhiView
 * @author WP
 * @brief 품앗이 모듈 프론트 뷰
 */
class poomahhiView extends poomahhi
{
	var $config = null;
	var $product_type = 'product';
	var $primary_module_srl = 0;

	function init()
	{
		$oModel = getModel('poomahhi');
		$this->config = $oModel->getModuleConfig();

		// mid 기반 product_type 자동 감지
		// local_poomahhi mid 접속 시 지역 품앗이, 그 외 상품 품앗이
		if($this->mid === 'local_poomahhi')
		{
			$this->product_type = 'local';
		}
		else
		{
			$this->product_type = 'product';
		}

		// 공유 데이터(카테고리, 채널, 지역, 확장변수 등)를 위해 메인 poomahhi module_srl 사용
		$oModuleModel = getModel('module');
		$main_module = $oModuleModel->getModuleInfoByMid('poomahhi');
		if($main_module && $main_module->module_srl)
		{
			$this->primary_module_srl = $main_module->module_srl;
		}
		else
		{
			$this->primary_module_srl = $this->module_srl;
		}

		// local_poomahhi·poomahhi_business일 때 메인 poomahhi와 동일한 레이아웃·스킨 적용 (헤더/메뉴 통일)
		if($main_module && ($this->mid === 'local_poomahhi' || $this->mid === 'poomahhi_business'))
		{
			$this->module_info->layout_srl = $main_module->layout_srl;
			if(isset($main_module->mlayout_srl)) $this->module_info->mlayout_srl = $main_module->mlayout_srl;
			if(!empty($main_module->skin)) $this->module_info->skin = $main_module->skin;
			if(isset($main_module->mskin) && $main_module->mskin !== '') $this->module_info->mskin = $main_module->mskin;
			Context::set('module_info', $this->module_info);
		}

		Context::set('product_type', $this->product_type);

		// 스킨 경로 설정 (다른 모듈 mid에서 호출되어도 poomahhi 모듈의 스킨 사용)
		$skin = 'default';
		if($this->module_info->module === 'poomahhi' && !empty($this->module_info->skin))
		{
			$skin = $this->module_info->skin;
		}
		elseif($main_module && !empty($main_module->skin))
		{
			$skin = $main_module->skin;
		}
		$template_path = sprintf('%sskins/%s/', $this->module_path, $skin);
		$this->setTemplatePath($template_path);
	}

	/**
	 * @brief 마이페이지 공통 헤더용 Context (회원 모듈 mid) 설정
	 * 관심 품앗이/내 신청 현황/내가 작성한 리뷰 페이지에서 회원정보 보기 등 링크용
	 */
	function _setMemberMenuHeaderContext()
	{
		Context::loadLang('./modules/member/lang');
		if(!Context::get('member_mid'))
		{
			$oModuleModel = getModel('module');
			$mid_list = $oModuleModel->getMidList(null, array('mid', 'module'));
			foreach($mid_list as $m)
			{
				if($m->module === 'member')
				{
					Context::set('member_mid', $m->mid);
					break;
				}
			}
		}
	}

	/**
	 * 비즈니스 센터 전용 화면용 Context (사이드바 링크, 활성 메뉴)
	 */
	function _setBusinessCenterContext($active_menu = '')
	{
		$this->_setMemberMenuHeaderContext();
		Context::set('business_active_menu', $active_menu);
		if(!Context::get('poomahhi_mid'))
		{
			$oModuleModel = getModel('module');
			$mid_list = $oModuleModel->getMidList(null, array('mid', 'module'));
			$fallback_mid = null;
			$local_mid = null;
			foreach($mid_list as $m)
			{
				if($m->module !== 'poomahhi')
				{
					continue;
				}
				if($m->mid === 'poomahhi')
				{
					Context::set('poomahhi_mid', $m->mid);
					$fallback_mid = null;
					break;
				}
				if($m->mid === 'local_poomahhi')
				{
					$local_mid = $m->mid;
				}
				elseif($m->mid !== 'poomahhi_business' && $fallback_mid === null)
				{
					$fallback_mid = $m->mid;
				}
			}
			if(!Context::get('poomahhi_mid'))
			{
				Context::set('poomahhi_mid', $local_mid ?: $fallback_mid);
			}
		}
		if(!Context::get('local_poomahhi_mid'))
		{
			$oModuleModel = getModel('module');
			$mid_list = $oModuleModel->getMidList(null, array('mid', 'module'));
			foreach($mid_list as $m)
			{
				if($m->module === 'poomahhi' && $m->mid === 'local_poomahhi')
				{
					Context::set('local_poomahhi_mid', $m->mid);
					break;
				}
			}
			if(!Context::get('local_poomahhi_mid'))
			{
				Context::set('local_poomahhi_mid', 'local_poomahhi');
			}
		}
		if(!Context::get('ncenterlite_mid'))
		{
			$oModuleModel = getModel('module');
			$mid_list = $oModuleModel->getMidList(null, array('mid', 'module'));
			foreach($mid_list as $m)
			{
				if($m->module === 'ncenterlite')
				{
					Context::set('ncenterlite_mid', $m->mid);
					break;
				}
			}
		}

		$logged_info = Context::get('logged_info');
		if($logged_info)
		{
			$oNcenterliteModel = getModel('ncenterlite');
			if($oNcenterliteModel && method_exists($oNcenterliteModel, '_getNewCount'))
			{
				Context::set('notify_unread_count', (int)$oNcenterliteModel->_getNewCount($logged_info->member_srl));
			}
			else
			{
				Context::set('notify_unread_count', 0);
			}
		}
		else
		{
			Context::set('notify_unread_count', 0);
		}
	}

	/**
	 * ncenterlite 알림 행에 표시용 텍스트·링크·배지 라벨을 채운다.
	 */
	function _enrichNcenterliteNotifyRows($list, $oNcenterliteModel)
	{
		if(!$list || !$oNcenterliteModel)
		{
			return;
		}
		if(!is_array($list))
		{
			$list = array($list);
		}
		$oMemberModel = getModel('member');
		foreach($list as $k => $v)
		{
			if(!is_object($v))
			{
				continue;
			}
			$v->text = $oNcenterliteModel->getNotificationText($v);
			$v->ago = $oNcenterliteModel->getAgo($v->regdate);
			$v->url = getUrl('', 'act', 'procNcenterliteRedirect', 'notify', $v->notify);
			if(isset($v->data))
			{
				$v->data = is_string($v->data) ? unserialize($v->data) : $v->data;
			}
			else
			{
				$v->data = array();
			}
			if(isset($v->target_member_srl) && $v->target_member_srl < 0)
			{
				$v->target_member_srl = 0;
			}
			if(!empty($v->target_member_srl))
			{
				$profileImage = $oMemberModel->getProfileImage($v->target_member_srl);
				$v->profileImage = $profileImage ? $profileImage->src : null;
			}
			else
			{
				$v->profileImage = null;
			}
			$v->notify_category_label = $this->_getNotifyCategoryLabelForBusiness($v);
			$v->notify_kind = $this->_classifyBusinessNotifyKind($v);
			$v->notify_display_prefix = $this->_getBusinessNotifyDisplayPrefix($v->notify_kind);
			$plain = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string)$v->text), ENT_QUOTES, 'UTF-8')));
			$v->notify_display_title = ($v->notify_display_prefix !== '') ? ($v->notify_display_prefix . ' ' . $plain) : $plain;
			$v->pmh_list_text = trim(preg_replace('/^\[[^\]]+\]\s+/u', '', (string)$v->notify_display_title));
			if($v->pmh_list_text === '')
			{
				$v->pmh_list_text = $plain;
			}
			$v->notify_icon_class = $this->_getNotifyIconClassForNotifyKind($v->notify_kind);
			$list[$k] = $v;
		}
	}

	/**
	 * 비즈니스 알림 목록용 내부 유형 (아이콘·표시 제목 접두어)
	 */
	function _classifyBusinessNotifyKind($v)
	{
		$target_type = isset($v->target_type) ? (string)$v->target_type : '';
		$type = isset($v->type) ? (string)$v->type : '';
		$url = isset($v->target_url) ? (string)$v->target_url : '';
		$body = isset($v->target_body) ? (string)$v->target_body : '';
		$text = isset($v->text) ? strip_tags((string)$v->text) : '';
		$hay = $body . ' ' . $text;

		if($target_type === 'B' && $type === 'X')
		{
			return 'poomahhi_broadcast';
		}
		if($target_type === 'B')
		{
			return 'system_notice_board';
		}
		if($target_type === 'E')
		{
			return 'ncenter_message';
		}

		if($type === 'X' && $url !== '' && stripos($url, 'poomahhi') !== false)
		{
			if(preg_match('/마감|D-\d+|D-Day/u', $hay))
			{
				return 'poomahhi_deadline';
			}
			if(stripos($hay, '참여 인증') !== false || stripos($hay, '인증 제출') !== false
				|| stripos($url, 'dispPoomahhiApplicationManageDetail') !== false)
			{
				return 'poomahhi_review';
			}
			if(stripos($hay, '품앗이 신청') !== false || stripos($hay, '신청이 왔습니다') !== false
				|| (stripos($url, 'dispPoomahhiApplicationManage') !== false && stripos($url, 'application_srl') === false))
			{
				return 'poomahhi_apply';
			}
			return 'poomahhi_generic';
		}

		return 'misc';
	}

	function _getBusinessNotifyDisplayPrefix($kind)
	{
		switch($kind)
		{
			case 'poomahhi_apply':
			case 'poomahhi_review':
			case 'poomahhi_deadline':
			case 'poomahhi_generic':
				return '[품앗이]';
			case 'poomahhi_broadcast':
			case 'system_notice_board':
				return '[시스템 공지]';
			case 'misc':
				return '[기타알림]';
			case 'ncenter_message':
				return '';
			default:
				return '';
		}
	}

	function _getNotifyIconClassForNotifyKind($kind)
	{
		switch($kind)
		{
			case 'poomahhi_apply':
			case 'poomahhi_generic':
				return 'bi-person-fill';
			case 'poomahhi_review':
				return 'bi-patch-check-fill';
			case 'poomahhi_deadline':
				return 'bi-calendar-event';
			case 'system_notice_board':
				return 'bi-gear';
			case 'poomahhi_broadcast':
				return 'bi-megaphone-fill';
			case 'ncenter_message':
				return 'bi-envelope-fill';
			case 'misc':
			default:
				return 'bi-bell-fill';
		}
	}

	function _getNotifyCategoryLabelForBusiness($v)
	{
		if(isset($v->target_type) && $v->target_type === 'B')
		{
			return '공지';
		}
		if(isset($v->type) && $v->type === 'X' && !empty($v->target_url) && stripos($v->target_url, 'poomahhi') !== false)
		{
			return '품앗이';
		}
		if(isset($v->type) && $v->type === 'X')
		{
			return '기타알림';
		}
		return '기타알림';
	}

	function _fillApplicationManageListRow($app)
	{
		$app->avatar_src = '';
		if(isset($app->member_info) && is_object($app->member_info) && !empty($app->member_info->profile_image))
		{
			$pi = $app->member_info->profile_image;
			if(is_object($pi) && !empty($pi->src))
			{
				$app->avatar_src = $pi->src;
			}
		}

		$g = isset($app->gender) ? (string)$app->gender : '';
		$app->gender_display = '';
		if($g !== '')
		{
			if($g === 'male' || $g === 'M' || $g === '남')
			{
				$app->gender_display = '남성';
			}
			elseif($g === 'female' || $g === 'F' || $g === '여')
			{
				$app->gender_display = '여성';
			}
			elseif($g === '남성' || $g === '여성')
			{
				$app->gender_display = $g;
			}
			else
			{
				$app->gender_display = $g;
			}
		}

		$bd = isset($app->birth_date) ? trim((string)$app->birth_date) : '';
		$app->birth_display = '';
		if($bd !== '')
		{
			if(preg_match('/^(\d{4})[\.\-\/](\d{1,2})[\.\-\/](\d{1,2})/', $bd, $m))
			{
				$app->birth_display = sprintf('%04d.%02d.%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
			}
			elseif(preg_match('/^(\d{8})/', $bd, $m))
			{
				$d = $m[1];
				$app->birth_display = substr($d, 0, 4) . '.' . substr($d, 4, 2) . '.' . substr($d, 6, 2);
			}
			else
			{
				$app->birth_display = $bd;
			}
		}
	}

	/**
	 * 품앗이 관리 목록용 단계 (상품 상태 + 일정 기준)
	 */
	function _getProductManagePhase($product, $todayYmd)
	{
		if($product->status === 'closed')
		{
			return 'completed';
		}
		$ae = $product->apply_end_date ?: $product->deadline_date;
		$ae8 = (strlen((string)$ae) >= 8) ? substr((string)$ae, 0, 8) : $todayYmd;
		if($ae8 >= $todayYmd)
		{
			return 'applying';
		}
		$rs = $product->review_start_date;
		$re = $product->review_end_date;
		if(strlen((string)$rs) >= 8 && strlen((string)$re) >= 8)
		{
			$rs8 = substr((string)$rs, 0, 8);
			$re8 = substr((string)$re, 0, 8);
			if($todayYmd >= $rs8 && $todayYmd <= $re8)
			{
				return 'review_wait';
			}
		}
		return 'selecting';
	}

	/**
	 * 품앗이 등록 유형 선택 화면 집계용: 완료 건수 (DB 종료 또는 리뷰 마감일 경과)
	 */
	function _isProductCompletedForWriteSelect($product, $todayYmd)
	{
		if($product->status === 'closed')
		{
			return true;
		}
		$re = $product->review_end_date;
		if($re && strlen((string)$re) >= 8)
		{
			$re8 = substr((string)$re, 0, 8);
			if((string)$todayYmd > (string)$re8)
			{
				return true;
			}
		}
		return false;
	}

	/**
	 * 한국어 마지막 음절에 따른 조사 '로' / '으로' (예: 초과로, 미충족으로)
	 */
	function _poomahhiKoreanRoParticle($phrase)
	{
		$phrase = trim((string)$phrase);
		if($phrase === '')
		{
			return '로';
		}
		if(!function_exists('mb_substr') || !function_exists('mb_ord'))
		{
			return '로';
		}
		$last = mb_substr($phrase, -1, 1, 'UTF-8');
		$code = mb_ord($last, 'UTF-8');
		if($code < 0xAC00 || $code > 0xD7A3)
		{
			return '로';
		}
		$local = $code - 0xAC00;
		return (($local % 28) !== 0) ? '으로' : '로';
	}

	/**
	 * DB/폼에서 오는 Ymd 또는 YmdHis 값을 Unix 시각으로 변환
	 */
	function _poomahhiYmdHisToTimestamp($ymdhis)
	{
		if(!$ymdhis)
		{
			return 0;
		}
		if(is_object($ymdhis) && method_exists($ymdhis, 'getTimestamp'))
		{
			return (int)$ymdhis->getTimestamp();
		}
		$s = preg_replace('/\D/', '', (string)$ymdhis);
		if(strlen($s) === 8)
		{
			$s .= '000000';
		}
		if(strlen($s) < 14)
		{
			$s = str_pad($s, 14, '0');
		}
		$dt = DateTime::createFromFormat('YmdHis', substr($s, 0, 14));
		return $dt ? (int)$dt->getTimestamp() : 0;
	}

	/**
	 * 비즈니스 홈 상단 알림(신청/리뷰·검수대기/마감임박/ncenter 미읽음 공지·기타 미읽음)
	 *
	 * @return array{array,bool,int|null}
	 */
	function _buildBusinessDashboardBanners($member_srl, $product_list, $recent_applications, $oModel)
	{
		$banners = array();
		$mid = $this->mid;
		$config = $this->config;
		$cutoff = strtotime('-24 hours');
		$dismiss_map = $this->_getBusinessBannerDismissCookieMap();

		$has_new_application_banner = false;
		$new_banner_product_srl = null;
		$has_review_dedicated_banner = false;
		$has_deadline_dedicated_banner = false;
		if($recent_applications)
		{
			foreach($recent_applications as $ra)
			{
				if(!is_object($ra) || !isset($ra->status) || $ra->status !== 'applied')
				{
					continue;
				}
				$rd = $this->_poomahhiYmdHisToTimestamp($ra->regdate);
				if($rd && $rd >= $cutoff)
				{
					if($this->_hasBusinessBannerDismissInCookie($dismiss_map, 'application', $ra->product_srl))
					{
						continue;
					}
					$has_new_application_banner = true;
					$new_banner_product_srl = $ra->product_srl;
					$banners[] = (object)array(
						'type' => 'application',
						'go_url' => getUrl('', 'mid', $mid, 'act', 'dispPoomahhiApplicationManage', 'product_srl', $ra->product_srl),
						'product_srl' => $ra->product_srl,
						'application_srl' => 0,
						'ncenter_notify' => '',
					);
					break;
				}
			}
		}

		$args_rev = new stdClass();
		$args_rev->member_srl = $member_srl;
		$args_rev->list_count = 40;
		$args_rev->page = 1;
		$rev_out = $oModel->getRecentReviewsByOrganizer($args_rev);
		$revs = $rev_out->data ?: array();
		if(!is_array($revs))
		{
			$revs = array($revs);
		}
		$oMemberModel = getModel('member');
		foreach($revs as $rv)
		{
			if(empty($rv->application_srl))
			{
				continue;
			}
			$app = $oModel->getApplication($rv->application_srl);
			if(!$app || $app->status !== 'under_review')
			{
				continue;
			}
			$ru = max(
				$this->_poomahhiYmdHisToTimestamp($rv->regdate),
				$this->_poomahhiYmdHisToTimestamp(isset($rv->last_update) ? $rv->last_update : 0)
			);
			if(!$ru || $ru < $cutoff)
			{
				continue;
			}
			if($this->_hasBusinessBannerDismissInCookie($dismiss_map, 'review', $rv->application_srl))
			{
				continue;
			}
			$mem = $oMemberModel->getMemberInfoByMemberSrl($app->member_srl);
			$nick = ($mem && !empty($mem->nick_name)) ? $mem->nick_name : '회원';
			$banners[] = (object)array(
				'type' => 'review',
				'go_url' => getUrl('', 'mid', $mid, 'act', 'dispPoomahhiApplicationManageDetail', 'application_srl', $app->application_srl),
				'applicant_nick' => $nick,
				'application_srl' => $app->application_srl,
				'product_srl' => $app->product_srl,
				'ncenter_notify' => '',
			);
			$has_review_dedicated_banner = true;
			break;
		}

		$deadline_max = isset($config->business_home_deadline_days) ? (int)$config->business_home_deadline_days : 7;
		if($deadline_max < 1)
		{
			$deadline_max = 7;
		}
		if($deadline_max > 90)
		{
			$deadline_max = 90;
		}
		$todayYmd = date('Ymd');
		$best_deadline = null;
		foreach($product_list as $p)
		{
			if($p->status !== 'active')
			{
				continue;
			}
			if($this->_hasBusinessBannerDismissInCookie($dismiss_map, 'deadline', $p->product_srl))
			{
				continue;
			}
			$src = $p->apply_end_date ?: $p->deadline_date;
			if(!$src)
			{
				continue;
			}
			$ymdhis = (strlen((string)$src) === 8) ? $src . '235959' : $src;
			$deadline_day = substr((string)$ymdhis, 0, 8);
			if(strlen($deadline_day) !== 8)
			{
				continue;
			}
			$deadline_ts = strtotime(
				substr($deadline_day, 0, 4) . '-' . substr($deadline_day, 4, 2) . '-' . substr($deadline_day, 6, 2)
			);
			$today_ts = strtotime(
				substr($todayYmd, 0, 4) . '-' . substr($todayYmd, 4, 2) . '-' . substr($todayYmd, 6, 2)
			);
			if($deadline_ts < $today_ts)
			{
				continue;
			}
			$days = (int)(($deadline_ts - $today_ts) / 86400);
			if($days < 0 || $days > $deadline_max)
			{
				continue;
			}
			if($best_deadline === null || $days < $best_deadline->days)
			{
				$ptitle = trim((string)$p->title);
				if($ptitle === '')
				{
					$ptitle = '#' . $p->product_srl;
				}
				$best_deadline = (object)array(
					'days' => $days,
					'title' => $ptitle,
					'product_srl' => $p->product_srl,
				);
			}
		}
		if($best_deadline)
		{
			$deadline_line = $oModel->formatPoomahhiDeadlineBannerLine($config, $best_deadline->title, $best_deadline->days);
			if($deadline_line === null)
			{
				$deadline_line = '';
			}
			$banners[] = (object)array(
				'type' => 'deadline',
				'go_url' => getUrl('', 'mid', $mid, 'act', 'dispPoomahhiProductDetail', 'product_srl', $best_deadline->product_srl),
				'days_remaining' => $best_deadline->days,
				'product_title' => $best_deadline->title,
				'product_srl' => $best_deadline->product_srl,
				'deadline_line' => $deadline_line,
				'application_srl' => 0,
				'ncenter_notify' => '',
			);
			$has_deadline_dedicated_banner = true;
		}

		$oNcenterliteModel = getModel('ncenterlite');
		if($oNcenterliteModel && method_exists($oNcenterliteModel, 'getNotificationText'))
		{
			$notice_out = $oModel->getBusinessNcenterUnreadNoticeForBanner($member_srl);
			if($notice_out && $notice_out->toBool() && !empty($notice_out->data))
			{
				$notice_rows = $notice_out->data;
				if(!is_array($notice_rows))
				{
					$notice_rows = array($notice_rows);
				}
				foreach($notice_rows as $nraw)
				{
					if(!is_object($nraw))
					{
						continue;
					}
					$tmp_notice = array($nraw);
					$this->_enrichNcenterliteNotifyRows($tmp_notice, $oNcenterliteModel);
					$nrow = $tmp_notice[0];
					if(!is_object($nrow))
					{
						continue;
					}
					$plain_notice = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string)$nrow->text), ENT_QUOTES, 'UTF-8')));
					if($plain_notice === '')
					{
						$fb = '';
						if(isset($nrow->target_body) && (string)$nrow->target_body !== '')
						{
							$fb = (string)$nrow->target_body;
						}
						elseif(isset($nrow->target_summary) && (string)$nrow->target_summary !== '')
						{
							$fb = (string)$nrow->target_summary;
						}
						$plain_notice = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($fb), ENT_QUOTES, 'UTF-8')));
					}
					if($plain_notice === '')
					{
						continue;
					}
					$disp = isset($nrow->notify_display_title) ? trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string)$nrow->notify_display_title), ENT_QUOTES, 'UTF-8'))) : '';
					if($disp === '' || $disp === trim((string)($nrow->notify_display_prefix ?? '')))
					{
						$prefix = isset($nrow->notify_display_prefix) ? (string)$nrow->notify_display_prefix : $this->_getBusinessNotifyDisplayPrefix(isset($nrow->notify_kind) ? $nrow->notify_kind : '');
						$disp = ($prefix !== '') ? trim($prefix . ' ' . $plain_notice) : $plain_notice;
					}
					$banners[] = (object)array(
						'type' => 'notice_ncenter',
						'go_url' => !empty($nrow->url) ? $nrow->url : getUrl('', 'mid', $mid, 'act', 'dispPoomahhiBusinessNotifications', 'notify_tab', 'notice'),
						'notify_text' => $disp,
						'ncenter_notify' => isset($nraw->notify) ? (string)$nraw->notify : '',
						'product_srl' => 0,
						'application_srl' => 0,
					);
					break;
				}
			}

			$nc_out = $oModel->getBusinessNcenterUnreadListForBanner($member_srl, 50);
			if($nc_out && $nc_out->toBool() && !empty($nc_out->data))
			{
				$nc_rows = $nc_out->data;
				if(!is_array($nc_rows))
				{
					$nc_rows = array($nc_rows);
				}
				$this->_enrichNcenterliteNotifyRows($nc_rows, $oNcenterliteModel);
				foreach($nc_rows as $nr)
				{
					$kind = isset($nr->notify_kind) ? (string)$nr->notify_kind : '';
					$skip_kinds = array('poomahhi_broadcast', 'system_notice_board');
					if(in_array($kind, $skip_kinds, true))
					{
						continue;
					}
					$dominated_by_dedicated = array();
					if($has_new_application_banner)
					{
						$dominated_by_dedicated[] = 'poomahhi_apply';
					}
					if($has_review_dedicated_banner)
					{
						$dominated_by_dedicated[] = 'poomahhi_review';
					}
					if($has_deadline_dedicated_banner)
					{
						$dominated_by_dedicated[] = 'poomahhi_deadline';
					}
					if(in_array($kind, $dominated_by_dedicated, true))
					{
						continue;
					}
					$raw_text = isset($nr->text) ? (string)$nr->text : '';
					$plain = trim(html_entity_decode(strip_tags($raw_text), ENT_QUOTES, 'UTF-8'));
					if($plain === '')
					{
						$plain = '새 알림이 있습니다.';
					}
					$disp_misc = isset($nr->notify_display_title) ? (string)$nr->notify_display_title : $plain;
					$banners[] = (object)array(
						'type' => 'ncenterlite_misc',
						'go_url' => !empty($nr->url) ? $nr->url : getUrl('', 'mid', $mid, 'act', 'dispPoomahhiBusinessNotifications'),
						'notify_text' => $disp_misc,
						'ncenter_notify' => isset($nr->notify) ? (string)$nr->notify : '',
						'product_srl' => 0,
						'application_srl' => 0,
					);
					break;
				}
			}
		}

		return array($banners, $has_new_application_banner, $new_banner_product_srl);
	}

	/**
	 * 비즈니스 홈 배너(신청·리뷰·마감) 숨김 쿠키 맵
	 */
	function _getBusinessBannerDismissCookieMap()
	{
		if(empty($_COOKIE['pmh_bbdismiss']) || !is_string($_COOKIE['pmh_bbdismiss']))
		{
			return array();
		}
		$decoded = json_decode($_COOKIE['pmh_bbdismiss'], true);
		return is_array($decoded) ? $decoded : array();
	}

	function _hasBusinessBannerDismissInCookie($map, $type, $srl)
	{
		if(!$map || (int)$srl < 1)
		{
			return false;
		}
		$key = $type . ':' . (int)$srl;
		return !empty($map[$key]);
	}

	/**
	 * @brief 품앗이 상품 목록
	 */
	function dispPoomahhiProductList()
	{
		$oModel = getModel('poomahhi');

		// mid 기반 품앗이 유형 필터 (init에서 설정)
		$product_type = $this->product_type;

		$category_list = $oModel->getCategoryList($this->primary_module_srl);
		$region_list = $oModel->getRegionList($this->primary_module_srl);

		$category_srl = Context::get('category_srl');
		$region_srl = Context::get('region_srl');

		// 탭과 목록 일치: URL에 없으면 첫 지역·첫 카테고리로 필터
		if($product_type === 'local' && $region_list && !$region_srl)
		{
			$first_rg = is_array($region_list) ? reset($region_list) : $region_list;
			if($first_rg && (int)$first_rg->region_srl > 0)
			{
				$region_srl = (int)$first_rg->region_srl;
				Context::set('region_srl', $region_srl);
			}
		}
		if($category_list && !$category_srl)
		{
			$first_cat = is_array($category_list) ? reset($category_list) : $category_list;
			if($first_cat && (int)$first_cat->category_srl > 0)
			{
				$category_srl = (int)$first_cat->category_srl;
				Context::set('category_srl', $category_srl);
			}
		}

		$args = new stdClass();
		$args->module_srl = $this->primary_module_srl;
		$args->page = Context::get('page') ?: 1;
		$args->list_count = $this->config->default_list_count ?: 20;

		$args->product_type = $product_type;

		if($category_srl) $args->category_srl = $category_srl;

		$shopping_channel = Context::get('shopping_channel');
		if($shopping_channel) $args->shopping_channel = $shopping_channel;

		if($region_srl) $args->region_srl = $region_srl;

		$search_keyword = Context::get('search_keyword');
		if($search_keyword) $args->search_keyword = '%' . $search_keyword . '%';

		// 정렬 옵션
		$sort = Context::get('sort');
		$use_popular_sort = ($sort === 'popular');
		if($sort === 'deadline')
		{
			$args->sort_index = 'deadline_date';
			$args->order = 'asc';
		}
		elseif($use_popular_sort)
		{
			$args->sort_index = 'wish_count';
			$args->order = 'desc';
		}

		$args->status = 'active';

		if($use_popular_sort)
		{
			$output = $oModel->getProductListByWishlistCount($args);
		}
		else
		{
			$output = $oModel->getProductList($args);
		}

		// 쇼핑채널 목록 (필터용)
		$channel_list = $oModel->getChannelList($this->primary_module_srl);

		// 지역 맵 생성 (region_srl => title, 리스트 카드에서 사용)
		$region_map = array();
		if($region_list)
		{
			foreach($region_list as $rg)
			{
				$region_map[$rg->region_srl] = $rg->title;
			}
		}

		// 위시리스트 정보 (로그인한 사용자) 및 D-day 계산
		$wishlist_map = array();
		$logged_info = Context::get('logged_info');

		if($output->data)
		{
			foreach($output->data as &$product)
			{
				// 위시리스트 체크
				if($logged_info)
				{
					$wishlist_item = $oModel->getWishlistItem($logged_info->member_srl, $product->product_srl);
					if($wishlist_item) $wishlist_map[$product->product_srl] = true;
				}

				// D-day 계산 (apply_end_date 우선, 없으면 deadline_date 사용)
				$dday_source = $product->apply_end_date ?: $product->deadline_date;
				if($dday_source)
				{
					$days = $oModel->getProductApplyDeadlineDayOffset($dday_source);
					if($days !== null)
					{
						$product->dday = $days;
						$product->dday_text = $oModel->getProductApplyDdayTextFromDayOffset($days);
					}
				}

				// 한 줄 설명 우선, 없으면 content에서 요약
				if($product->short_description)
				{
					$product->content_summary = $product->short_description;
				}
				elseif($product->content)
				{
					$product->content_summary = mb_strimwidth(strip_tags($product->content), 0, 80, '...');
				}

				// 지역명 추가 (지역 품앗이용)
				if($product->region_srl && isset($region_map[$product->region_srl]))
				{
					$product->region_title = $region_map[$product->region_srl];
				}
			}
		}

		Context::set('product_list', $output->data);
		Context::set('page_navigation', $output->page_navigation);
		Context::set('category_list', $category_list);
		Context::set('channel_list', $channel_list);
		Context::set('region_list', $region_list);
		Context::set('region_map', $region_map);
		Context::set('wishlist_map', $wishlist_map);
		Context::set('total_count', $output->total_count);
		Context::set('product_type', $product_type);

		$this->setTemplateFile('product_list');
	}

	/**
	 * @brief 통합 검색 결과 (상품 + 지역 품앗이)
	 */
	function dispPoomahhiSearchResult()
	{
		$search_keyword = trim(Context::get('search_keyword'));
		if(!$search_keyword)
		{
			header('Location: ' . getNotEncodedUrl('', 'mid', $this->mid, 'act', 'dispPoomahhiProductList'));
			exit;
		}

		$oModel = getModel('poomahhi');
		$oModuleModel = getModel('module');

		$module_srl_list = array();
		$search_module_srl_list = trim(Context::get('search_module_srl_list'));
		if($search_module_srl_list !== '')
		{
			$parts = array_map('trim', explode(',', $search_module_srl_list));
			foreach($parts as $part)
			{
				if(is_numeric($part))
				{
					$srl = (int)$part;
					if($srl > 0 && !in_array($srl, $module_srl_list))
					{
						$module_srl_list[] = $srl;
					}
				}
			}
		}
		if(empty($module_srl_list))
		{
			$main_module = $oModuleModel->getModuleInfoByMid('poomahhi');
			$local_module = $oModuleModel->getModuleInfoByMid('local_poomahhi');
			if($main_module && $main_module->module_srl)
			{
				$module_srl_list[] = (int)$main_module->module_srl;
			}
			if($local_module && $local_module->module_srl)
			{
				$srl = (int)$local_module->module_srl;
				if(!in_array($srl, $module_srl_list))
				{
					$module_srl_list[] = $srl;
				}
			}
		}
		if(empty($module_srl_list))
		{
			$module_srl_list = array($this->primary_module_srl);
		}

		$region_map = array();
		foreach($module_srl_list as $msrl)
		{
			$region_list = $oModel->getRegionList($msrl);
			if($region_list)
			{
				foreach($region_list as $rg)
				{
					$region_map[$rg->region_srl] = $rg->title;
				}
			}
		}

		$logged_info = Context::get('logged_info');
		$wishlist_map = array();

		$args_base = new stdClass();
		$args_base->module_srl_list = implode(',', $module_srl_list);
		$args_base->search_keyword = '%' . $search_keyword . '%';
		$args_base->status = 'active';
		$args_base->list_count = 15;
		$args_base->page = 1;

		$args_product = clone $args_base;
		$args_product->product_type = 'product';
		$output_product = $oModel->getProductListSearch($args_product);

		$args_local = clone $args_base;
		$args_local->product_type = 'local';
		$output_local = $oModel->getProductListSearch($args_local);

		$product_list = $output_product->data ?: array();
		$local_list = $output_local->data ?: array();

		$enrich = function(&$item) use ($oModel, $logged_info, $region_map, &$wishlist_map)
		{
			if($logged_info)
			{
				$wishlist_item = $oModel->getWishlistItem($logged_info->member_srl, $item->product_srl);
				if($wishlist_item) $wishlist_map[$item->product_srl] = true;
			}
			$dday_source = $item->apply_end_date ?: $item->deadline_date;
			if($dday_source)
			{
				$days = $oModel->getProductApplyDeadlineDayOffset($dday_source);
				if($days !== null)
				{
					$item->dday = $days;
					$item->dday_text = $oModel->getProductApplyDdayTextFromDayOffset($days);
				}
			}
			if($item->short_description)
			{
				$item->content_summary = $item->short_description;
			}
			elseif($item->content)
			{
				$item->content_summary = mb_strimwidth(strip_tags($item->content), 0, 80, '...');
			}
			if($item->region_srl && isset($region_map[$item->region_srl]))
			{
				$item->region_title = $region_map[$item->region_srl];
			}
			$item->is_paid = (($item->content_access_type ?: 'public') === 'paid' && (int)($item->point_cost ?: 0) > 0);
			$item->point_cost_display = (int)($item->point_cost ?: 0);
		};

		foreach($product_list as &$item) $enrich($item);
		foreach($local_list as &$item) $enrich($item);

		Context::set('search_keyword', $search_keyword);
		Context::set('product_list', $product_list);
		Context::set('local_list', $local_list);
		Context::set('wishlist_map', $wishlist_map);
		Context::set('total_product_count', $output_product->total_count);
		Context::set('total_local_count', $output_local->total_count);

		$this->setTemplateFile('search_result');
	}

	/**
	 * @brief 품앗이 상세
	 */
	function dispPoomahhiProductDetail()
	{
		$product_srl = Context::get('product_srl');
		if(!$product_srl) return $this->stop('상품을 찾을 수 없습니다.');

		$oModel = getModel('poomahhi');
		$product = $oModel->getProduct($product_srl);
		if(!$product) return $this->stop('상품을 찾을 수 없습니다.');

		$extra_vars = $oModel->getProductExtraVars($product_srl);

		$category = null;
		if($product->category_srl) $category = $oModel->getCategory($product->category_srl);

		$application_count = $oModel->getApplicationCount($product_srl);

		$is_wished = false;
		$logged_info = Context::get('logged_info');
		if($logged_info)
		{
			$wishlist_item = $oModel->getWishlistItem($logged_info->member_srl, $product_srl);
			$is_wished = !empty($wishlist_item);
		}

		$oFileModel = getModel('file');
		$files = $oFileModel->getFiles($product_srl);

		$dday_text = '';
		$is_application_closed = false;
		$dday_source = $product->apply_end_date ?: $product->deadline_date;
		if($dday_source)
		{
			$deadline_ymdhis = (strlen($dday_source) === 8) ? $dday_source . '235959' : $dday_source;
			if(date('YmdHis') > $deadline_ymdhis) $is_application_closed = true;
			$days = $oModel->getProductApplyDeadlineDayOffset($dday_source);
			if($days !== null)
			{
				$dday_text = $oModel->getProductApplyDdayTextFromDayOffset($days);
			}
		}

		$mission_tags = array();
		if($product->mission_tags)
		{
			$tags = explode(',', $product->mission_tags);
			foreach($tags as $t) { $t = trim($t); if($t) $mission_tags[] = $t; }
		}

		// 지역 정보 (지역 품앗이용)
		$region = null;
		if($product->region_srl)
		{
			$region = $oModel->getRegion($product->region_srl);
		}

		// 콘텐츠 접근 권한 (public/private/paid)
		$content_access_type = $product->content_access_type ?: 'public';
		$point_cost = (int)($product->point_cost ?: 0);
		$has_content_access = false;
		$show_lock_ui = false;
		$require_login = false;

		if($content_access_type === 'public')
		{
			$has_content_access = true;
		}
		else
		{
			if(!$logged_info)
			{
				$require_login = true;
			}
			else
			{
				$is_owner = ($product->member_srl == $logged_info->member_srl);
				$is_admin = !empty($logged_info->is_admin) && $logged_info->is_admin === 'Y';
				if($is_owner || $is_admin)
				{
					$has_content_access = true;
				}
				elseif($content_access_type === 'private')
				{
					$has_content_access = true;
				}
				elseif($content_access_type === 'paid')
				{
					$view_access = $oModel->getProductViewAccess($product_srl, $logged_info->member_srl);
					if($view_access)
					{
						$has_content_access = true;
					}
					else
					{
						$show_lock_ui = true;
					}
				}
			}
		}

		Context::set('product', $product);
		Context::set('extra_vars', $extra_vars);
		Context::set('has_content_access', $has_content_access);
		Context::set('show_lock_ui', $show_lock_ui);
		Context::set('require_login', $require_login);
		Context::set('content_point_cost', $point_cost);
		Context::set('current_url', \Rhymix\Framework\URL::getCurrentURL());
		Context::set('category', $category);
		Context::set('application_count', $application_count);
		Context::set('is_wished', $is_wished);
		Context::set('files', $files);
		Context::set('dday_text', $dday_text);
		Context::set('is_application_closed', $is_application_closed);
		Context::set('mission_tags', $mission_tags);
		Context::set('region', $region);

		$this->setTemplateFile('product_detail');
	}

	/**
	 * @brief 품앗이 등록/수정 폼
	 */
	function dispPoomahhiProductWrite()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return $this->stop('로그인이 필요합니다.');

		$oController = getController('poomahhi');
		if(!$oController->_isBusinessMember($logged_info) && !$oController->_isAdmin($logged_info))
		{
			return $this->stop('권한이 없습니다.');
		}

		$oModel = getModel('poomahhi');
		$product = null;
		$extra_vars = array();
		$product_srl = Context::get('product_srl');

		// 품앗이 유형 결정 (mid 기반 자동 감지, 수정 시 기존 상품의 product_type 우선)
		$product_type = $this->product_type;
		$req_product_type = Context::get('product_type');
		if(!$product_srl && $req_product_type && in_array($req_product_type, array('product', 'local'), true))
		{
			$product_type = $req_product_type;
		}

		if($product_srl)
		{
			$product = $oModel->getProduct($product_srl);
			if(!$product) return $this->stop('상품을 찾을 수 없습니다.');
			if($product->member_srl != $logged_info->member_srl && !$oController->_isAdmin($logged_info))
			{
				return $this->stop('권한이 없습니다.');
			}
			$extra_vars = $oModel->getProductExtraVars($product_srl);

			// 수정 시: 기존 상품의 product_type 사용
			if($product->product_type) $product_type = $product->product_type;
		}

		// 카테고리 목록 (메인 모듈 기준 공유 데이터)
		$category_list = $oModel->getCategoryList($this->primary_module_srl);

		// 쇼핑채널 목록 (어드민에서 동적 등록)
		$channel_list = $oModel->getChannelList($this->primary_module_srl);

		// 지역 목록 (어드민에서 동적 등록)
		$region_list = $oModel->getRegionList($this->primary_module_srl);

		// 확장변수 템플릿 목록
		$extra_template_list = $oModel->getExtraTemplateList($this->primary_module_srl);

		// 수정 시: 상품에 지정된 템플릿의 확장변수 정의 로드
		$extra_def_list = array();
		if($product && $product->extra_template_srl)
		{
			$def_args = new stdClass();
			$def_args->template_srl = $product->extra_template_srl;
			$def_output = executeQueryArray('poomahhi.getExtraDefList', $def_args);
			$extra_def_list = ($def_output->toBool() && $def_output->data) ? $def_output->data : array();
		}

		// 확장변수를 name => value 맵으로 변환
		$extra_var_map = array();
		if($extra_vars)
		{
			foreach($extra_vars as $ev)
			{
				$extra_var_map[$ev->var_name] = $ev->var_value;
			}
		}

		// 에디터 컴포넌트 설정
		// 새 글: product_srl을 미리 생성하여 에디터와 동일한 upload_target_srl 사용
		// 수정: 기존 product_srl 사용
		if($product)
		{
			$upload_target_srl = $product->product_srl;
		}
		else
		{
			$upload_target_srl = getNextSequence();
		}

		$oEditorModel = getModel('editor');
		$option = new stdClass();
		$option->primary_key_name = 'product_srl';
		$option->content_key_name = 'content';
		$option->allow_fileupload = true;
		$option->enable_autosave = false;
		$option->enable_default_component = true;
		$option->enable_component = true;
		$option->resizable = true;
		$option->height = 400;
		$option->module_srl = $this->primary_module_srl;
		$editor = $oEditorModel->getEditor($upload_target_srl, $option);

		Context::set('product', $product);
		Context::set('product_type', $product_type);
		Context::set('upload_target_srl', $upload_target_srl);
		Context::set('category_list', $category_list);
		Context::set('channel_list', $channel_list);
		Context::set('region_list', $region_list);
		Context::set('extra_template_list', $extra_template_list);
		Context::set('extra_def_list', $extra_def_list);
		Context::set('extra_var_map', $extra_var_map);
		Context::set('editor', $editor);

		// 품앗이 유형에 따라 템플릿 분기
		if($product_type === 'local')
		{
			$this->setTemplateFile('product_write_local');
		}
		else
		{
			$this->setTemplateFile('product_write_product');
		}
	}

	/**
	 * @brief 품앗이 신청하기 폼
	 */
	function dispPoomahhiApplicationWrite()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return $this->stop('로그인이 필요합니다.');

		$product_srl = Context::get('product_srl');
		if(!$product_srl) return $this->stop('상품을 찾을 수 없습니다.');

		$oModel = getModel('poomahhi');
		$product = $oModel->getProduct($product_srl);
		if(!$product) return $this->stop('상품을 찾을 수 없습니다.');

		// 신청 마감일 지났으면 신청 폼 진입 불가 (apply_end_date 우선, 없으면 deadline_date)
		$deadline_source = $product->apply_end_date ?: $product->deadline_date;
		if($deadline_source)
		{
			$deadline_ymdhis = (strlen($deadline_source) === 8) ? $deadline_source . '235959' : $deadline_source;
			if(date('YmdHis') > $deadline_ymdhis)
			{
				return $this->stop('신청 마감일이 지났습니다.');
			}
		}

		// 이미 해당 상품에 활성 신청이 있으면 상세 페이지로 리다이렉트
		$existing_count = $oModel->getActiveApplicationCount($product_srl, $logged_info->member_srl);
		if($existing_count > 0)
		{
			$existing = $oModel->getActiveApplicationByProductAndMember($product_srl, $logged_info->member_srl);
			if($existing && $existing->application_srl)
			{
				$redirect_url = getNotEncodedUrl('', 'mid', $this->mid, 'act', 'dispPoomahhiApplicationDetail', 'application_srl', $existing->application_srl);
				header('Location: ' . $redirect_url);
				exit;
			}
			return $this->stop('이미 신청하셨습니다.');
		}

		// 신청 수
		$application_count = $oModel->getApplicationCount($product_srl);

		// D-day 계산
		$dday_text = '';
		$dday_source = $product->apply_end_date ?: $product->deadline_date;
		if($dday_source)
		{
			$days = $oModel->getProductApplyDeadlineDayOffset($dday_source);
			if($days !== null)
			{
				$dday_text = $oModel->getProductApplyDdayTextFromDayOffset($days);
			}
		}

		// 회원 정보 (기본값 세팅)
		$oMemberModel = getModel('member');
		$member_info = $oMemberModel->getMemberInfoByMemberSrl($logged_info->member_srl);
		if(!$member_info) $member_info = new stdClass();

		// 개인정보 수집이용 동의 내용 (모듈 설정)
		$privacy_content = $this->config->privacy_content ?: '';

		Context::set('product', $product);
		Context::set('application_count', $application_count);
		Context::set('dday_text', $dday_text);
		Context::set('member_info', $member_info);
		Context::set('privacy_content', $privacy_content);

		$this->setTemplateFile('application_write');
	}

	/**
	 * @brief 내 신청 현황 (6단계 필터)
	 */
	function dispPoomahhiApplicationList()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return $this->stop('로그인이 필요합니다.');

		$oModel = getModel('poomahhi');

		$args = new stdClass();
		$args->member_srl = $logged_info->member_srl;
		$args->page = Context::get('page') ?: 1;
		$args->list_count = $this->config->default_list_count ?: 20;

		$status = Context::get('status');
		if($status === 'rejected')
		{
			$args->status_list = 'rejected,cancelled';
		}
		elseif($status)
		{
			$args->status = $status;
		}

		$output = $oModel->getApplicationListByMember($args);
		if($output->data)
		{
			foreach($output->data as &$app)
			{
				$app->product = $oModel->getProduct($app->product_srl);
				if($app->product && !property_exists($app->product, 'region_title'))
				{
					$oModel->attachProductRegionTitle($app->product);
				}
				$app->review = $oModel->getReviewByApplication($app->application_srl);

				if($app->product)
				{
					$p = $app->product;
					if(!empty($p->product_image) && strpos($p->product_image, 'http') !== 0)
					{
						$app->product_image_url = '/' . ltrim($p->product_image, '/');
					}
					else
					{
						$app->product_image_url = isset($p->product_image) ? $p->product_image : '';
					}

					$dday_source = $p->apply_end_date ?: $p->deadline_date;
					if($dday_source)
					{
						$days = $oModel->getProductApplyDeadlineDayOffset($dday_source);
						if($days !== null)
						{
							$app->dday = $oModel->getProductApplyDdayTextFromDayOffset($days);
						}
					}
				}

				$status_labels = array(
					'applied' => '신청한 품앗이',
					'selected' => '선정된 품앗이',
					'under_review' => '검수중인 품앗이',
					'revision_requested' => '수정요청된 품앗이',
					'completed' => '완료된 품앗이',
					'rejected' => '미선정된 품앗이',
					'cancelled' => '취소된 품앗이',
				);
				$app->status_label = isset($status_labels[$app->status]) ? $status_labels[$app->status] : $app->status;

				$app->action_list = array();
				if(in_array($app->status, array('selected', 'under_review', 'revision_requested')))
				{
					$deadline_str = '';
					$dday_action = '';
					if(!empty($app->deadline))
					{
						$dl = $app->deadline;
						$dl_ts = strtotime(substr($dl, 0, 4) . '-' . substr($dl, 4, 2) . '-' . substr($dl, 6, 2));
						$diff = ceil(($dl_ts - strtotime(date('Y-m-d'))) / 86400);
						$dday_action = ($diff > 0) ? 'D-' . $diff : (($diff == 0) ? 'D-Day' : '');
						$deadline_str = zdate($app->deadline, 'Y.m.d');
					}
					$is_done = !empty($app->review);
					$detail_url = getUrl('', 'mid', Context::get('mid'), 'act', 'dispPoomahhiApplicationDetail', 'application_srl', $app->application_srl);
					$certification_substatus_label = '';
					if($app->status === 'revision_requested')
					{
						$status_label = '대기 >';
						$action_label = '수정요청 확인';
						$action_url = $detail_url;
						$certification_substatus_label = '인증 제출 상태 수정요청';
					}
					else
					{
						$status_label = $is_done ? '완료' : '대기 >';
						$action_label = $is_done ? '참여인증 보기' : '참여인증 등록하기';
						$action_url = $is_done
							? $detail_url
							: getUrl('', 'mid', Context::get('mid'), 'act', 'dispPoomahhiReviewWrite', 'application_srl', $app->application_srl);
					}
					$action_done_ui = ($app->status === 'revision_requested') ? false : $is_done;
					$app->action_list[] = (object)array(
						'label' => $action_label,
						'dday' => $dday_action,
						'deadline_date' => $deadline_str,
						'done' => $action_done_ui,
						'status_label' => $status_label,
						'url' => $action_url,
						'certification_substatus_label' => $certification_substatus_label,
						'detail_url' => $detail_url,
					);
				}
			}
			unset($app);
		}

		$status_counts = $oModel->getApplicationStatusCountsByMember($logged_info->member_srl);

		$current_status_safe = ($status !== null && $status !== '' && is_scalar($status)) ? (string)$status : '';
		$lang_app = (object)array(
			'page_title' => '품앗이 현황',
			'status_applied' => '신청한 품앗이',
			'status_selected' => '선정된 품앗이',
			'status_under_review' => '검수중인 품앗이',
			'status_revision_requested' => '수정요청된 품앗이',
			'status_completed' => '완료된 품앗이',
			'status_rejected_cancelled' => '미선정·취소된 품앗이',
			'product_purchase_link' => '구매 링크',
			'contact' => '연락처',
			'address' => '주소',
			'btn_cancel' => '취소',
		);
		Context::set('lang_app', $lang_app);
		Context::set('page_title', $lang_app->page_title);
		Context::set('application_list', $output->data ?: array());
		Context::set('total_count', $output->total_count);
		Context::set('current_status', $current_status_safe);
		Context::set('status_counts', $status_counts);
		Context::set('page_navigation', $output->page_navigation);

		$this->_setMemberMenuHeaderContext();
		$this->setTemplateFile('application_list');
	}

	/**
	 * @brief 신청 관리 (비즈니스/어드민)
	 */
	function dispPoomahhiApplicationManage()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return $this->stop('로그인이 필요합니다.');

		$product_srl = Context::get('product_srl');
		if(!$product_srl) return $this->stop('상품을 찾을 수 없습니다.');

		$oModel = getModel('poomahhi');
		$product = $oModel->getProduct($product_srl);
		if(!$product) return $this->stop('상품을 찾을 수 없습니다.');

		$oController = getController('poomahhi');
		if($product->member_srl != $logged_info->member_srl && !$oController->_isAdmin($logged_info))
		{
			return $this->stop('권한이 없습니다.');
		}

		$manage_tab = Context::get('manage_tab');
		$legacy_status = Context::get('status');
		if(!$manage_tab && $legacy_status !== null && $legacy_status !== '')
		{
			if(in_array((string)$legacy_status, array('rejected', 'cancelled'), true))
			{
				$manage_tab = 'rejected_cancelled';
			}
			else
			{
				$manage_tab = (string)$legacy_status;
			}
		}
		if(!$manage_tab) $manage_tab = 'applied';

		$valid_tabs = array('applied', 'selected', 'under_review', 'revision_requested', 'completed', 'rejected_cancelled');
		if(!in_array($manage_tab, $valid_tabs, true))
		{
			$manage_tab = 'applied';
		}

		$page = (int)(Context::get('page') ?: 1);
		if($page < 1) $page = 1;
		$search_keyword = trim((string)Context::get('search_keyword'));
		$list_count_cfg = (int)($this->config->default_list_count ?: 20);
		if($list_count_cfg < 1) $list_count_cfg = 20;

		$args = new stdClass();
		$args->product_srl = $product_srl;
		if($search_keyword !== '')
		{
			$args->page = 1;
			$args->list_count = 5000;
		}
		else
		{
			$args->page = $page;
			$args->list_count = $list_count_cfg;
		}

		if($manage_tab === 'rejected_cancelled')
		{
			$args->status_list = array('rejected', 'cancelled');
			$output = $oModel->getApplicationsByProductStatusIn($args);
		}
		else
		{
			$args->status = $manage_tab;
			$output = $oModel->getApplicationsByProduct($args);
		}

		$oMemberModel = getModel('member');
		if($output->data)
		{
			if(!is_array($output->data))
			{
				$output->data = array($output->data);
			}
			foreach($output->data as &$app)
			{
				$app->member_info = $oMemberModel->getMemberInfoByMemberSrl($app->member_srl);
				$app->review = $oModel->getReviewByApplication($app->application_srl);
				$app->member_review = $oModel->getMemberReviewByApplication($app->application_srl);
				$this->_fillApplicationManageListRow($app);
			}
			unset($app);
		}

		if($search_keyword !== '')
		{
			$rows = $output->data;
			if($rows)
			{
				if(!is_array($rows))
				{
					$rows = array($rows);
				}
				$filtered = array();
				foreach($rows as $app)
				{
					$parts = array();
					if(isset($app->applicant_name)) $parts[] = $app->applicant_name;
					if(isset($app->applicant_comment)) $parts[] = $app->applicant_comment;
					if(isset($app->phone)) $parts[] = $app->phone;
					if($app->member_info)
					{
						if(isset($app->member_info->nick_name)) $parts[] = $app->member_info->nick_name;
						if(isset($app->member_info->user_id)) $parts[] = $app->member_info->user_id;
					}
					$blob = implode(' ', $parts);
					if(mb_stripos($blob, $search_keyword, 0, 'UTF-8') !== false)
					{
						$filtered[] = $app;
					}
				}
				$total_count = count($filtered);
				$total_page = max(1, (int)ceil($total_count / $list_count_cfg));
				if($page > $total_page) $page = $total_page;
				$offset = ($page - 1) * $list_count_cfg;
				$output->data = array_slice($filtered, $offset, $list_count_cfg);
				$output->total_count = $total_count;
				$page_nav = new stdClass();
				$page_nav->first_page = 1;
				$page_nav->last_page = $total_page;
				$page_nav->cur_page = $page;
				$page_nav->total_page = $total_page;
				$output->page_navigation = $page_nav;
			}
			else
			{
				$output->data = array();
				$output->total_count = 0;
				$page_nav = new stdClass();
				$page_nav->first_page = 1;
				$page_nav->last_page = 1;
				$page_nav->cur_page = 1;
				$page_nav->total_page = 1;
				$output->page_navigation = $page_nav;
			}
		}

		$product_total_applications = $oModel->getApplicationCount($product_srl);
		$product_application_counts = $oModel->getApplicationStatusCountsByProduct($product_srl);

		$region_list = $oModel->getRegionList($this->primary_module_srl);
		$region_map = array();
		if($region_list)
		{
			$rl = is_array($region_list) ? $region_list : array($region_list);
			foreach($rl as $rg)
			{
				$region_map[$rg->region_srl] = $rg->title;
			}
		}
		if($product->product_type === 'local' && !empty($product->region_srl) && isset($region_map[$product->region_srl]))
		{
			Context::set('product_list_tag_label', $region_map[$product->region_srl]);
		}
		else
		{
			Context::set('product_list_tag_label', $product->shopping_channel ? $product->shopping_channel : '');
		}

		Context::set('product', $product);
		Context::set('application_list', $output->data);
		Context::set('page_navigation', $output->page_navigation);
		Context::set('total_count', $output->total_count);
		Context::set('current_status', $manage_tab);
		Context::set('manage_tab', $manage_tab);
		Context::set('product_total_applications', $product_total_applications);
		Context::set('product_application_counts', $product_application_counts);
		Context::set('am_search_keyword', $search_keyword);
		Context::set('page', $page);

		$this->_setBusinessCenterContext('manage');
		$this->setTemplateFile('application_manage');
	}

	/**
	 * @brief 신청 관리 상세 (등록자가 개별 신청 건 열람)
	 */
	function dispPoomahhiApplicationManageDetail()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return $this->stop('로그인이 필요합니다.');

		$application_srl = Context::get('application_srl');
		if(!$application_srl) return $this->stop('신청 내역을 찾을 수 없습니다.');

		$oModel = getModel('poomahhi');
		$application = $oModel->getApplication($application_srl);
		if(!$application) return $this->stop('신청 내역을 찾을 수 없습니다.');

		$product = $oModel->getProduct($application->product_srl);
		if(!$product) return $this->stop('상품을 찾을 수 없습니다.');

		$oController = getController('poomahhi');
		if($product->member_srl != $logged_info->member_srl && !$oController->_isAdmin($logged_info))
		{
			return $this->stop('권한이 없습니다.');
		}

		$oMemberModel = getModel('member');
		$applicant_member = $oMemberModel->getMemberInfoByMemberSrl($application->member_srl);

		$review = $oModel->getReviewByApplication($application_srl);

		$review_certification_files = array();
		$review_purchase_files = array();
		if($review)
		{
			$make_file_objs = function($paths) {
				$list = array();
				foreach($paths as $path)
				{
					$path = trim($path);
					if($path === '') continue;
					$obj = new stdClass();
					$obj->download_url = '/' . ltrim(preg_replace('#^.*?files/attach/#', 'files/attach/', $path), '/');
					$obj->source_filename = basename($path);
					$obj->mime_type = 'image/jpeg';
					$list[] = $obj;
				}
				return $list;
			};
			if(!empty($review->certification_attachment_paths))
				$review_certification_files = $make_file_objs(explode(',', $review->certification_attachment_paths));
			if(!empty($review->purchase_attachment_paths))
				$review_purchase_files = $make_file_objs(explode(',', $review->purchase_attachment_paths));
			if(empty($review_certification_files) && empty($review_purchase_files) && !empty($review->attachment_paths))
			{
				$legacy = array_map('trim', explode(',', $review->attachment_paths));
				$legacy = array_values(array_filter($legacy));
				$review_certification_files = $make_file_objs(array_slice($legacy, 0, 6));
				$review_purchase_files = $make_file_objs(array_slice($legacy, 6, 6));
			}
		}

		$existing_member_review = $oModel->getMemberReviewByApplication($application_srl);

		$extra_vars = $oModel->getProductExtraVars($application->product_srl);

		$return_url = Context::get('success_return_url');
		if(!$return_url) $return_url = getUrl('', 'mid', Context::get('mid'), 'act', 'dispPoomahhiApplicationManageDetail', 'application_srl', $application->application_srl);
		Context::set('form_success_return_url', $return_url);

		Context::set('application', $application);
		Context::set('product', $product);
		Context::set('applicant_member', $applicant_member);
		Context::set('review', $review);
		Context::set('review_certification_files', $review_certification_files);
		Context::set('review_purchase_files', $review_purchase_files);
		Context::set('existing_member_review', $existing_member_review);
		Context::set('extra_vars', $extra_vars);

		if($oController->_isBusinessMember($logged_info))
		{
			$this->_setBusinessCenterContext('manage');
			Context::set('use_business_layout', true);
		}

		$this->setTemplateFile('application_manage_detail');
	}

	/**
	 * @brief 품앗이 현황 상세 (개별 신청건 상세 정보)
	 */
	function dispPoomahhiApplicationDetail()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return $this->stop('로그인이 필요합니다.');

		$application_srl = Context::get('application_srl');
		if(!$application_srl) return $this->stop('신청 내역을 찾을 수 없습니다.');

		$oModel = getModel('poomahhi');
		$application = $oModel->getApplication($application_srl);
		if(!$application) return $this->stop('신청 내역을 찾을 수 없습니다.');

		// 본인 신청건 또는 관리자만 열람 가능
		$oController = getController('poomahhi');
		if($application->member_srl != $logged_info->member_srl && !$oController->_isAdmin($logged_info))
		{
			return $this->stop('권한이 없습니다.');
		}

		// 상품 정보
		$product = $oModel->getProduct($application->product_srl);

		// 리뷰 정보
		$review = $oModel->getReviewByApplication($application_srl);

		$review_certification_files = array();
		$review_purchase_files = array();
		if($review)
		{
			$make_file_objs = function($paths) {
				$list = array();
				foreach($paths as $path)
				{
					$path = trim($path);
					if($path === '') continue;
					$obj = new stdClass();
					$obj->download_url = '/' . ltrim(preg_replace('#^.*?files/attach/#', 'files/attach/', $path), '/');
					$obj->source_filename = basename($path);
					$obj->mime_type = 'image/jpeg';
					$list[] = $obj;
				}
				return $list;
			};
			if(!empty($review->certification_attachment_paths))
				$review_certification_files = $make_file_objs(explode(',', $review->certification_attachment_paths));
			if(!empty($review->purchase_attachment_paths))
				$review_purchase_files = $make_file_objs(explode(',', $review->purchase_attachment_paths));
			if(empty($review_certification_files) && empty($review_purchase_files) && !empty($review->attachment_paths))
			{
				$legacy = array_map('trim', explode(',', $review->attachment_paths));
				$legacy = array_values(array_filter($legacy));
				$review_certification_files = $make_file_objs(array_slice($legacy, 0, 6));
				$review_purchase_files = $make_file_objs(array_slice($legacy, 6, 6));
			}
		}

		// 상품 확장변수
		$extra_vars = $oModel->getProductExtraVars($application->product_srl);

		// 카테고리 정보
		$category = null;
		if($product && $product->category_srl)
		{
			$category = $oModel->getCategory($product->category_srl);
		}

		// D-day 계산
		$dday_text = '';
		$dday_src = $product->apply_end_date ?: $product->deadline_date;
		if($product && $dday_src)
		{
			$days = $oModel->getProductApplyDeadlineDayOffset($dday_src);
			if($days !== null)
			{
				$dday_text = $oModel->getProductApplyDdayTextFromDayOffset($days);
			}
		}

		Context::set('application', $application);
		Context::set('product', $product);
		Context::set('review', $review);
		Context::set('review_certification_files', $review_certification_files);
		Context::set('review_purchase_files', $review_purchase_files);
		Context::set('extra_vars', $extra_vars);
		Context::set('category', $category);
		Context::set('dday_text', $dday_text);

		$rejection_josa_ro = '로';
		if($application->status === 'rejected')
		{
			$rr = isset($application->rejection_reason) ? trim((string)$application->rejection_reason) : '';
			if($rr !== '')
			{
				$rejection_josa_ro = $this->_poomahhiKoreanRoParticle($rr);
			}
		}
		Context::set('rejection_josa_ro', $rejection_josa_ro);

		$this->setTemplateFile('application_detail');
	}

	/**
	 * @brief 리뷰 등록 폼
	 */
	function dispPoomahhiReviewWrite()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return $this->stop('로그인이 필요합니다.');

		$application_srl = Context::get('application_srl');
		if(!$application_srl) return $this->stop('신청 내역을 찾을 수 없습니다.');

		$oModel = getModel('poomahhi');
		$application = $oModel->getApplication($application_srl);
		if(!$application) return $this->stop('신청 내역을 찾을 수 없습니다.');

		if($application->member_srl != $logged_info->member_srl)
		{
			return $this->stop('권한이 없습니다.');
		}

		$product = $oModel->getProduct($application->product_srl);
		// 상품이 삭제된 경우에도 카드 영역이 보이도록 빈 객체 사용
		if(!$product)
		{
			$product = new stdClass();
			$product->product_srl = 0;
			$product->title = '';
			$product->company_name = '';
			$product->product_image = '';
			$product->product_url = '';
			$product->product_type = 'local';
		}
		// 대표이미지 절대 URL (상대 경로일 때 루트 기준으로 변환)
		if(!empty($product->product_image) && strpos($product->product_image, 'http') !== 0)
		{
			$product->product_image_url = '/' . ltrim($product->product_image, '/');
		}
		else
		{
			$product->product_image_url = isset($product->product_image) ? $product->product_image : '';
		}

		$product->card_dday = '';
		if($product && (int)$product->product_srl > 0)
		{
			$dday_source = !empty($product->apply_end_date) ? $product->apply_end_date : (isset($product->deadline_date) ? $product->deadline_date : '');
			if($dday_source)
			{
				$days = $oModel->getProductApplyDeadlineDayOffset($dday_source);
				if($days !== null)
				{
					$product->card_dday = $oModel->getProductApplyDdayTextFromDayOffset($days);
				}
			}
		}

		$lang_app = (object)array(
			'product_purchase_link' => '구매 링크',
			'contact' => '연락처',
			'address' => '주소',
		);
		Context::set('lang_app', $lang_app);

		// 품앗이 현황 대시보드(이미지1)용 상태별 건수
		$status_counts = $oModel->getApplicationStatusCountsByMember($logged_info->member_srl);
		Context::set('status_counts', $status_counts);
		Context::set('current_status', 'selected');

		// 파일 첨부용 upload_target_srl (리뷰 등록 시 동일 값을 review_srl로 사용)
		$upload_target_srl = getNextSequence();
		Context::set('upload_target_srl', $upload_target_srl);

		// 임시저장 데이터 (있으면 폼에 반영)
		$draft = $oModel->getReviewDraftByApplication((int)$application_srl);
		$draft_content_escaped = '';
		$draft_score = 5;
		$draft_cert_urls = array('', '', '', '', '', '');
		$draft_purchase_urls = array('', '', '', '', '', '');
		if($draft)
		{
			$score_val = isset($draft->score) ? (int)$draft->score : 5;
			if($score_val < 1 || $score_val > 5) $score_val = 5;
			$draft_score = $score_val;
			$cert_paths_str = isset($draft->certification_attachment_paths) ? (string)$draft->certification_attachment_paths : '';
			$purchase_paths_str = isset($draft->purchase_attachment_paths) ? (string)$draft->purchase_attachment_paths : '';
			if($cert_paths_str === '' && $purchase_paths_str === '' && !empty($draft->attachment_paths))
			{
				$legacy = array();
				foreach(explode(',', $draft->attachment_paths) as $path)
				{
					$path = trim($path);
					if($path) $legacy[] = $path;
				}
				$cert_paths_str = implode(',', array_slice($legacy, 0, 6));
				$purchase_paths_str = implode(',', array_slice($legacy, 6, 6));
			}
			$base = rtrim(\RX_BASEURL, '/') . '/';
			foreach(array('cert' => $cert_paths_str, 'purchase' => $purchase_paths_str) as $kind => $str)
			{
				if($str === '') continue;
				$arr = &$draft_cert_urls;
				if($kind === 'purchase') $arr = &$draft_purchase_urls;
				$i = 0;
				foreach(explode(',', $str) as $path)
				{
					$path = trim($path);
					if(!$path || $i >= 6) continue;
					$norm = preg_replace('#^.*?files/attach/#', 'files/attach/', $path);
					$arr[$i] = $base . ltrim($norm, '/');
					$i++;
				}
			}
			if(isset($draft->content) && $draft->content !== '')
			{
				$draft_content_escaped = htmlspecialchars($draft->content, ENT_QUOTES, 'UTF-8');
			}
		}

		Context::set('application', $application);
		Context::set('product', $product);
		Context::set('config', $this->config);
		Context::set('is_review_edit', false);
		Context::set('draft', $draft);
		Context::set('draft_content_escaped', $draft_content_escaped);
		Context::set('draft_score', $draft_score);
		for($i = 0; $i < 6; $i++)
		{
			Context::set('draft_cert_' . $i, $draft_cert_urls[$i]);
			Context::set('draft_purchase_' . $i, $draft_purchase_urls[$i]);
		}

		$this->setTemplateFile('review_write');
	}

	/**
	 * @brief 참여 인증(리뷰) 수정 폼 (수정요청 상태, 신청자 본인만)
	 */
	function dispPoomahhiReviewEdit()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return $this->stop('로그인이 필요합니다.');

		$application_srl = Context::get('application_srl');
		if(!$application_srl) return $this->stop('신청 내역을 찾을 수 없습니다.');

		$oModel = getModel('poomahhi');
		$application = $oModel->getApplication($application_srl);
		if(!$application) return $this->stop('신청 내역을 찾을 수 없습니다.');

		if($application->member_srl != $logged_info->member_srl)
			return $this->stop('권한이 없습니다.');

		if($application->status !== 'revision_requested')
			return $this->stop('수정 요청된 품앗이만 수정할 수 있습니다.');

		$review = $oModel->getReviewByApplication($application_srl);
		if(!$review) return $this->stop('잘못된 요청입니다.');

		$product = $oModel->getProduct($application->product_srl);
		if(!$product)
		{
			$product = new stdClass();
			$product->product_srl = 0;
			$product->title = '';
			$product->company_name = '';
			$product->product_image = '';
			$product->product_url = '';
			$product->product_type = 'local';
		}
		if(!empty($product->product_image) && strpos($product->product_image, 'http') !== 0)
			$product->product_image_url = '/' . ltrim($product->product_image, '/');
		else
			$product->product_image_url = isset($product->product_image) ? $product->product_image : '';

		$product->card_dday = '';
		if($product && (int)$product->product_srl > 0)
		{
			$dday_source = !empty($product->apply_end_date) ? $product->apply_end_date : (isset($product->deadline_date) ? $product->deadline_date : '');
			if($dday_source)
			{
				$days = $oModel->getProductApplyDeadlineDayOffset($dday_source);
				if($days !== null)
				{
					$product->card_dday = $oModel->getProductApplyDdayTextFromDayOffset($days);
				}
			}
		}

		$lang_app = (object)array(
			'product_purchase_link' => '구매 링크',
			'contact' => '연락처',
			'address' => '주소',
		);
		Context::set('lang_app', $lang_app);

		$status_counts = $oModel->getApplicationStatusCountsByMember($logged_info->member_srl);
		Context::set('status_counts', $status_counts);
		Context::set('current_status', 'revision_requested');

		$draft_content_escaped = isset($review->content) && $review->content !== ''
			? htmlspecialchars($review->content, ENT_QUOTES, 'UTF-8') : '';
		$draft_score = isset($review->score) ? max(1, min(5, (int)$review->score)) : 5;

		$draft_cert_urls = array('', '', '', '', '', '');
		$draft_purchase_urls = array('', '', '', '', '', '');
		$base = rtrim(\RX_BASEURL, '/') . '/';
		$cert_str = isset($review->certification_attachment_paths) ? (string)$review->certification_attachment_paths : '';
		$purchase_str = isset($review->purchase_attachment_paths) ? (string)$review->purchase_attachment_paths : '';
		if($cert_str !== '')
		{
			$i = 0;
			foreach(explode(',', $cert_str) as $path)
			{
				$path = trim($path);
				if($path === '' || $i >= 6) continue;
				$norm = preg_replace('#^.*?files/attach/#', 'files/attach/', $path);
				$draft_cert_urls[$i] = $base . ltrim($norm, '/');
				$i++;
			}
		}
		if($purchase_str !== '')
		{
			$i = 0;
			foreach(explode(',', $purchase_str) as $path)
			{
				$path = trim($path);
				if($path === '' || $i >= 6) continue;
				$norm = preg_replace('#^.*?files/attach/#', 'files/attach/', $path);
				$draft_purchase_urls[$i] = $base . ltrim($norm, '/');
				$i++;
			}
		}

		Context::set('application', $application);
		Context::set('product', $product);
		Context::set('config', $this->config);
		Context::set('review', $review);
		Context::set('is_review_edit', true);
		Context::set('upload_target_srl', $review->review_srl);
		Context::set('draft', null);
		Context::set('draft_content_escaped', $draft_content_escaped);
		Context::set('draft_score', $draft_score);
		for($i = 0; $i < 6; $i++)
		{
			Context::set('draft_cert_' . $i, $draft_cert_urls[$i]);
			Context::set('draft_purchase_' . $i, $draft_purchase_urls[$i]);
		}

		$this->setTemplateFile('review_write');
	}

	/**
	 * @brief 본인이 작성한 품앗이 리뷰 목록
	 */
	function dispPoomahhiMyReviews()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return $this->stop('로그인이 필요합니다.');

		$member_srl = $logged_info->member_srl;
		$oModel = getModel('poomahhi');
		$oMemberModel = getModel('member');

		$review_stats = $oModel->getMemberReviewStats($member_srl);
		$review_stats->avg_score = $review_stats->avg_score ? round($review_stats->avg_score, 1) : 0;

		$score_dist = $oModel->getMemberReviewScoreDistribution($member_srl);
		$total_reviews = (int)$review_stats->review_count;
		$score_distribution = new stdClass();
		for($i = 5; $i >= 1; $i--)
		{
			$count_key = 'score_' . $i . '_count';
			$percent_key = 'score_' . $i . '_percent';
			$count = isset($score_dist->$count_key) ? (int)$score_dist->$count_key : 0;
			$score_distribution->$count_key = $count;
			$score_distribution->$percent_key = $total_reviews > 0 ? round(($count / $total_reviews) * 100) : 0;
		}

		$ranking = $oModel->getMemberReviewRanking($member_srl);

		$level = 1;
		$avg = (float)$review_stats->avg_score;
		if($avg >= 4.5) $level = 5;
		elseif($avg >= 3.5) $level = 4;
		elseif($avg >= 2.5) $level = 3;
		elseif($avg >= 1.5) $level = 2;

		$fulfillment_stats = $oModel->getFulfillmentStats($member_srl);
		$fulfilled = (int)$fulfillment_stats->fulfilled_count;
		$unfulfilled = (int)$fulfillment_stats->unfulfilled_count;
		$fulfillment_total = $fulfilled + $unfulfilled;
		$fulfillment_stats->total_count = $fulfillment_total;
		$fulfillment_stats->fulfilled_percent = $fulfillment_total > 0 ? round(($fulfilled / $fulfillment_total) * 100) : 0;
		$fulfillment_stats->unfulfilled_percent = $fulfillment_total > 0 ? round(($unfulfilled / $fulfillment_total) * 100) : 0;

		$args = new stdClass();
		$args->member_srl = $member_srl;
		$args->page = Context::get('page') ?: 1;
		$args->list_count = $this->config->default_list_count;
		$review_output = $oModel->getReviewListByMemberWithProduct($args);
		$oController = getController('poomahhi');

		if($review_output->data)
		{
			foreach($review_output->data as &$review)
			{
				$review->reviewer_nickname = $logged_info->nick_name;
				$review->reviewer_profile_image = null;
				if(!empty($logged_info->profile_image) && !empty($logged_info->profile_image->src))
				{
					$review->reviewer_profile_image = $logged_info->profile_image->src;
				}

				if($review->product_type == 'local' && $review->region_srl)
				{
					$region = $oModel->getRegion($review->region_srl);
					$review->region_title = $region ? $region->title : '';
				}

				$dday_src = !empty($review->apply_end_date) ? $review->apply_end_date : $review->deadline_date;
				if(!empty($dday_src))
				{
					$deadline_ts = strtotime(
						substr($dday_src, 0, 4) . '-' .
						substr($dday_src, 4, 2) . '-' .
						substr($dday_src, 6, 2)
					);
					$diff = ceil(($deadline_ts - strtotime(date('Y-m-d'))) / 86400);
					$review->dday = ($diff > 0) ? 'D-' . $diff : (($diff == 0) ? 'D-Day' : '마감');
				}

				$review->has_application = false;
				if(!empty($review->application_srl))
				{
					$review->has_application = (bool)$oModel->getApplication((int)$review->application_srl);
				}

				$review->member_review = $oModel->getMemberReviewByApplication($review->application_srl);
				$review->can_edit_member_review = false;
				if($review->member_review)
				{
					$mr_member = $oMemberModel->getMemberInfoByMemberSrl($review->member_review->reviewer_member_srl);
					$review->member_review->reviewer_nickname = $mr_member ? $mr_member->nick_name : '';
					$review->member_review->reviewer_profile_image = null;
					if($mr_member && !empty($mr_member->profile_image) && !empty($mr_member->profile_image->src))
					{
						$review->member_review->reviewer_profile_image = $mr_member->profile_image->src;
					}
					$review->can_edit_member_review = ((int)$review->member_review->reviewer_member_srl === (int)$logged_info->member_srl) || $oController->_isAdmin($logged_info);

					$mr_rpt_args = new stdClass();
					$mr_rpt_args->review_srl = (int)$review->member_review->review_srl;
					$mr_rpt_args->review_type = 'member_review';
					$mr_rpt_args->reporter_member_srl = (int)$logged_info->member_srl;
					$mr_rpt_out = executeQuery('poomahhi.getReviewReport', $mr_rpt_args);
					$review->member_review->is_reported = ($mr_rpt_out->toBool() && $mr_rpt_out->data) ? true : false;
				}
				$review->is_product_owner = ($logged_info->member_srl == $review->product_owner_srl);
				$review->member_review_validator_id = 'modules/poomahhi/member_review_' . $review->application_srl;

				$replies = $oModel->getReviewReplies($review->review_srl);
				if($replies)
				{
					foreach($replies as &$rp)
					{
						$rp_member = $oMemberModel->getMemberInfoByMemberSrl($rp->member_srl);
						$rp->nick_name = $rp_member ? $rp_member->nick_name : '';
						$rp->profile_image = null;
						if($rp_member && !empty($rp_member->profile_image) && !empty($rp_member->profile_image->src))
						{
							$rp->profile_image = $rp_member->profile_image->src;
						}
						$rp_rpt_args = new stdClass();
						$rp_rpt_args->review_srl = (int)$rp->reply_srl;
						$rp_rpt_args->review_type = 'review_reply';
						$rp_rpt_args->reporter_member_srl = (int)$logged_info->member_srl;
						$rp_rpt_out = executeQuery('poomahhi.getReviewReport', $rp_rpt_args);
						$rp->is_reported = ($rp_rpt_out->toBool() && $rp_rpt_out->data) ? true : false;
					}
					unset($rp);
				}
				$review->review_replies = $replies;
			}
		}

		Context::set('review_stats', $review_stats);
		Context::set('score_distribution', $score_distribution);
		Context::set('ranking', $ranking);
		Context::set('member_level', $level);
		Context::set('fulfillment_stats', $fulfillment_stats);
		Context::set('review_list', $review_output->data);
		Context::set('total_count', $review_output->total_count);
		Context::set('total_page', $review_output->total_page);
		Context::set('page', (int)$args->page);
		Context::set('page_navigation', $review_output->page_navigation);

		Context::set('pmh_my_reviews_lang_report_prompt', htmlspecialchars(lang('poomahhi.my_reviews_report_reason_prompt'), ENT_QUOTES, 'UTF-8'));
		Context::set('pmh_my_reviews_lang_delete_confirm', htmlspecialchars(lang('poomahhi.my_reviews_comment_delete_confirm'), ENT_QUOTES, 'UTF-8'));
		Context::set('pmh_my_reviews_lang_edit_empty', htmlspecialchars(lang('poomahhi.my_reviews_comment_edit_empty'), ENT_QUOTES, 'UTF-8'));
		Context::set('pmh_my_reviews_mid_esc', htmlspecialchars((string)$this->mid, ENT_QUOTES, 'UTF-8'));
		$mod_srl = isset($this->module_info->module_srl) ? (int)$this->module_info->module_srl : (int)$this->module_srl;
		if($mod_srl > 0)
		{
			Context::set('pmh_my_reviews_post_action', htmlspecialchars(getNotEncodedUrl('', 'mid', $this->mid, 'module_srl', $mod_srl), ENT_QUOTES, 'UTF-8'));
		}
		else
		{
			Context::set('pmh_my_reviews_post_action', htmlspecialchars(getNotEncodedUrl('', 'mid', $this->mid), ENT_QUOTES, 'UTF-8'));
		}

		if($oController->_isBusinessMember($logged_info))
		{
			Context::set('use_business_layout', true);
			$this->_setBusinessCenterContext('received_reviews');
		}
		else
		{
			Context::set('use_business_layout', false);
			$this->_setMemberMenuHeaderContext();
		}
		$this->setTemplateFile('my_reviews');
	}

	/**
	 * @brief 내 포인트 (본인)
	 */
	function dispPoomahhiMyPoints()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return $this->stop('로그인이 필요합니다.');

		$member_srl = $logged_info->member_srl;
		$oModel = getModel('poomahhi');

		$balance = $oModel->getRhymixPointBalance($member_srl);
		$point_level = 0;
		if(class_exists('PointModel'))
		{
			$oModuleModel = getModel('module');
			$point_config = $oModuleModel->getModuleConfig('point');
			$level_step = ($point_config && isset($point_config->level_step)) ? $point_config->level_step : array();
			$point_level = PointModel::getLevel($balance, $level_step);
		}
		$point_summary = (object)array(
			'point' => $balance,
			'point_formatted' => number_format($balance, 0, '.', ','),
			'level' => $point_level,
		);

		$year = (int)Context::get('year') ?: (int)date('Y');
		$month = (int)Context::get('month') ?: (int)date('n');
		$type_filter = Context::get('type') ?: 'all';
		$page = (int)Context::get('page') ?: 1;

		$args = new stdClass();
		$args->member_srl = $member_srl;
		$args->year = $year;
		$args->month = $month;
		$args->type_filter = ($type_filter === 'earn' || $type_filter === 'deduct') ? $type_filter : 'all';
		$args->page = $page;
		$args->list_count = $this->config->default_list_count;

		$log_output = $oModel->getRhymixPointhistoryLogPage($args);

		Context::set('point_summary', $point_summary);
		Context::set('point_log_list', $log_output->data ?: array());
		Context::set('total_count', $log_output->total_count);
		Context::set('page', isset($log_output->page) ? $log_output->page : $page);
		Context::set('page_navigation', $log_output->page_navigation);
		Context::set('current_year', $year);
		Context::set('current_month', $month);
		Context::set('current_type', $type_filter);
		Context::set('rhymix_point_history_available', $oModel->isRhymixPointhistoryAvailable());

		$this->_setMemberMenuHeaderContext();
		$this->setTemplateFile('my_points');
	}

	/**
	 * @brief 특정 회원이 작성한 리뷰 목록 (공개)
	 */
	function dispPoomahhiMemberReviews()
	{
		$target_member_srl = Context::get('target_member_srl');
		if(!$target_member_srl) return $this->stop('잘못된 요청입니다.');

		$oMemberModel = getModel('member');
		$target_member = $oMemberModel->getMemberInfoByMemberSrl($target_member_srl);
		if(!$target_member || !$target_member->member_srl)
		{
			return $this->stop('회원을 찾을 수 없습니다.');
		}

		$logged_info = Context::get('logged_info');
		$oModel = getModel('poomahhi');

		$review_stats = $oModel->getMemberReviewStats($target_member_srl);
		$review_stats->avg_score = $review_stats->avg_score ? round($review_stats->avg_score, 1) : 0;

		$score_dist = $oModel->getMemberReviewScoreDistribution($target_member_srl);
		$total_reviews = (int)$review_stats->review_count;
		$score_distribution = new stdClass();
		for($i = 5; $i >= 1; $i--)
		{
			$count_key = 'score_' . $i . '_count';
			$percent_key = 'score_' . $i . '_percent';
			$count = isset($score_dist->$count_key) ? (int)$score_dist->$count_key : 0;
			$score_distribution->$count_key = $count;
			$score_distribution->$percent_key = $total_reviews > 0 ? round(($count / $total_reviews) * 100) : 0;
		}

		$ranking = $oModel->getMemberReviewRanking($target_member_srl);
		$avg = (float)$review_stats->avg_score;
		$level = 1;
		if($avg >= 4.5) $level = 5;
		elseif($avg >= 3.5) $level = 4;
		elseif($avg >= 2.5) $level = 3;
		elseif($avg >= 1.5) $level = 2;

		$fulfillment_stats = $oModel->getFulfillmentStats($target_member_srl);
		$fulfilled = (int)$fulfillment_stats->fulfilled_count;
		$unfulfilled = (int)$fulfillment_stats->unfulfilled_count;
		$fulfillment_total = $fulfilled + $unfulfilled;
		$fulfillment_stats->total_count = $fulfillment_total;
		$fulfillment_stats->fulfilled_percent = $fulfillment_total > 0 ? round(($fulfilled / $fulfillment_total) * 100) : 0;
		$fulfillment_stats->unfulfilled_percent = $fulfillment_total > 0 ? round(($unfulfilled / $fulfillment_total) * 100) : 0;

		$args = new stdClass();
		$args->member_srl = $target_member_srl;
		$args->page = Context::get('page') ?: 1;
		$args->list_count = $this->config->default_list_count;
		$review_output = $oModel->getReviewListByMemberWithProduct($args);

		if($review_output->data)
		{
			foreach($review_output->data as &$review)
			{
				$review->reviewer_nickname = $target_member ? $target_member->nick_name : '';
				$review->reviewer_profile_image = null;
				if($target_member && !empty($target_member->profile_image) && !empty($target_member->profile_image->src))
				{
					$review->reviewer_profile_image = $target_member->profile_image->src;
				}
				if($review->product_type == 'local' && $review->region_srl)
				{
					$region = $oModel->getRegion($review->region_srl);
					$review->region_title = $region ? $region->title : '';
				}
				if($review->deadline_date)
				{
					$deadline_ts = strtotime(
						substr($review->deadline_date, 0, 4) . '-' .
						substr($review->deadline_date, 4, 2) . '-' .
						substr($review->deadline_date, 6, 2)
					);
					$diff = ceil(($deadline_ts - strtotime(date('Y-m-d'))) / 86400);
					$review->dday = ($diff > 0) ? 'D-' . $diff : (($diff == 0) ? 'D-Day' : '마감');
				}
				$review->member_review = $oModel->getMemberReviewByApplication($review->application_srl);
				if($review->member_review)
				{
					$mr_member = $oMemberModel->getMemberInfoByMemberSrl($review->member_review->reviewer_member_srl);
					$review->member_review->reviewer_nickname = $mr_member ? $mr_member->nick_name : '';
					$review->member_review->reviewer_profile_image = null;
					if($mr_member && !empty($mr_member->profile_image) && !empty($mr_member->profile_image->src))
					{
						$review->member_review->reviewer_profile_image = $mr_member->profile_image->src;
					}
				}
				$review->is_product_owner = ($logged_info && $logged_info->member_srl == $review->product_owner_srl);
				$review->member_review_validator_id = 'modules/poomahhi/member_review_' . $review->application_srl;
			}
		}

		Context::set('target_member', $target_member);
		Context::set('review_stats', $review_stats);
		Context::set('score_distribution', $score_distribution);
		Context::set('ranking', $ranking);
		Context::set('member_level', $level);
		Context::set('fulfillment_stats', $fulfillment_stats);
		Context::set('review_list', $review_output->data);
		Context::set('total_count', $review_output->total_count);
		Context::set('total_page', $review_output->total_page);
		Context::set('page', (int)$args->page);
		Context::set('page_navigation', $review_output->page_navigation);

		$this->setTemplateFile('member_reviews');
	}

	/**
	 * @brief 특정 회원이 받은 평가(회원평가) 목록 (공개)
	 */
	function dispPoomahhiMemberReceivedReviews()
	{
		$target_member_srl = Context::get('target_member_srl');
		if(!$target_member_srl) return $this->stop('잘못된 요청입니다.');

		$oMemberModel = getModel('member');
		$target_member = $oMemberModel->getMemberInfoByMemberSrl($target_member_srl);
		if(!$target_member || !$target_member->member_srl)
		{
			return $this->stop('회원을 찾을 수 없습니다.');
		}

		$oModel = getModel('poomahhi');

		$review_stats = $oModel->getMemberReviewStats($target_member_srl);
		$review_stats->avg_score = $review_stats->avg_score ? round($review_stats->avg_score, 1) : 0;

		$score_dist = $oModel->getMemberReviewScoreDistribution($target_member_srl);
		$total_reviews = (int)$review_stats->review_count;
		$score_distribution = new stdClass();
		for($i = 5; $i >= 1; $i--)
		{
			$count_key = 'score_' . $i . '_count';
			$percent_key = 'score_' . $i . '_percent';
			$count = isset($score_dist->$count_key) ? (int)$score_dist->$count_key : 0;
			$score_distribution->$count_key = $count;
			$score_distribution->$percent_key = $total_reviews > 0 ? round(($count / $total_reviews) * 100) : 0;
		}

		$ranking = $oModel->getMemberReviewRanking($target_member_srl);
		$avg = (float)$review_stats->avg_score;
		$level = 1;
		if($avg >= 4.5) $level = 5;
		elseif($avg >= 3.5) $level = 4;
		elseif($avg >= 2.5) $level = 3;
		elseif($avg >= 1.5) $level = 2;

		$fulfillment_stats = $oModel->getFulfillmentStats($target_member_srl);
		$fulfilled = (int)$fulfillment_stats->fulfilled_count;
		$unfulfilled = (int)$fulfillment_stats->unfulfilled_count;
		$fulfillment_total = $fulfilled + $unfulfilled;
		$fulfillment_stats->total_count = $fulfillment_total;
		$fulfillment_stats->fulfilled_percent = $fulfillment_total > 0 ? round(($fulfilled / $fulfillment_total) * 100) : 0;
		$fulfillment_stats->unfulfilled_percent = $fulfillment_total > 0 ? round(($unfulfilled / $fulfillment_total) * 100) : 0;

		$logged_info = Context::get('logged_info');

		$args = new stdClass();
		$args->target_member_srl = $target_member_srl;
		$args->page = Context::get('page') ?: 1;
		$args->list_count = $this->config->default_list_count;
		$review_output = $oModel->getMemberReviewListByTarget($args);

		if($review_output->data)
		{
			$oController = getController('poomahhi');
			foreach($review_output->data as &$row)
			{
				$rev = $oMemberModel->getMemberInfoByMemberSrl($row->reviewer_member_srl);
				$row->reviewer_nickname = $rev ? $rev->nick_name : '';
				$row->reviewer_profile_image = null;
				if($rev && !empty($rev->profile_image) && !empty($rev->profile_image->src))
				{
					$row->reviewer_profile_image = $rev->profile_image->src;
				}
				if(isset($row->product_type) && $row->product_type == 'local' && !empty($row->region_srl))
				{
					$region = $oModel->getRegion($row->region_srl);
					$row->region_title = $region ? $region->title : '';
				}
				else
				{
					$row->region_title = '';
				}
				$dday_src = !empty($row->apply_end_date) ? $row->apply_end_date : $row->deadline_date;
				if(!empty($dday_src))
				{
					$deadline_ts = strtotime(
						substr($dday_src, 0, 4) . '-' .
						substr($dday_src, 4, 2) . '-' .
						substr($dday_src, 6, 2)
					);
					$diff = ceil(($deadline_ts - strtotime(date('Y-m-d'))) / 86400);
					$row->dday = ($diff > 0) ? 'D-' . $diff : (($diff == 0) ? 'D-Day' : '마감');
				}
				$row->has_application = !empty($row->application_srl);

				$mr_reported = false;
				if($logged_info)
				{
					$mr_rpt_args = new stdClass();
					$mr_rpt_args->review_srl = (int)$row->review_srl;
					$mr_rpt_args->review_type = 'member_review';
					$mr_rpt_args->reporter_member_srl = (int)$logged_info->member_srl;
					$mr_rpt_out = executeQuery('poomahhi.getReviewReport', $mr_rpt_args);
					$mr_reported = ($mr_rpt_out->toBool() && $mr_rpt_out->data) ? true : false;
				}
				$row->member_review = (object)array(
					'review_srl' => $row->review_srl,
					'is_reported' => $mr_reported
				);

				$row->can_edit = ($logged_info && (int)$row->reviewer_member_srl === (int)$logged_info->member_srl) || ($logged_info && $oController->_isAdmin($logged_info));
				$row->can_reply = ($logged_info && (int)$target_member_srl === (int)$logged_info->member_srl);

				$participant_review = $oModel->getReviewByApplication($row->application_srl);
				$row->participant_review_srl = $participant_review ? (int)$participant_review->review_srl : 0;

				$row->review_replies = array();
				if($row->participant_review_srl)
				{
					$replies = $oModel->getReviewReplies($row->participant_review_srl);
					if($replies)
					{
						foreach($replies as &$rp)
						{
							$rp_member = $oMemberModel->getMemberInfoByMemberSrl($rp->member_srl);
							$rp->nick_name = $rp_member ? $rp_member->nick_name : '';
							$rp->profile_image = null;
							if($rp_member && !empty($rp_member->profile_image) && !empty($rp_member->profile_image->src))
							{
								$rp->profile_image = $rp_member->profile_image->src;
							}
							$rp->is_reported = false;
							if($logged_info)
							{
								$rp_rpt_args = new stdClass();
								$rp_rpt_args->review_srl = (int)$rp->reply_srl;
								$rp_rpt_args->review_type = 'review_reply';
								$rp_rpt_args->reporter_member_srl = (int)$logged_info->member_srl;
								$rp_rpt_out = executeQuery('poomahhi.getReviewReport', $rp_rpt_args);
								$rp->is_reported = ($rp_rpt_out->toBool() && $rp_rpt_out->data) ? true : false;
							}
						}
						unset($rp);
					}
					$row->review_replies = $replies ?: array();
				}
			}
			unset($row);
		}

		Context::set('target_member', $target_member);
		Context::set('review_stats', $review_stats);
		Context::set('score_distribution', $score_distribution);
		Context::set('ranking', $ranking);
		Context::set('member_level', $level);
		Context::set('fulfillment_stats', $fulfillment_stats);
		Context::set('review_list', $review_output->data ?: array());
		Context::set('total_count', $review_output->total_count);
		Context::set('total_page', $review_output->total_page);
		Context::set('page', (int)$args->page);
		Context::set('page_navigation', $review_output->page_navigation);

		Context::set('pmh_my_reviews_lang_delete_confirm', htmlspecialchars(lang('poomahhi.my_reviews_comment_delete_confirm'), ENT_QUOTES, 'UTF-8'));
		Context::set('pmh_my_reviews_lang_edit_empty', htmlspecialchars(lang('poomahhi.my_reviews_comment_edit_empty'), ENT_QUOTES, 'UTF-8'));
		Context::set('pmh_my_reviews_mid_esc', htmlspecialchars((string)$this->mid, ENT_QUOTES, 'UTF-8'));
		$mod_srl = isset($this->module_info->module_srl) ? (int)$this->module_info->module_srl : (int)$this->module_srl;
		if($mod_srl > 0)
		{
			Context::set('pmh_my_reviews_post_action', htmlspecialchars(getNotEncodedUrl('', 'mid', $this->mid, 'module_srl', $mod_srl), ENT_QUOTES, 'UTF-8'));
		}
		else
		{
			Context::set('pmh_my_reviews_post_action', htmlspecialchars(getNotEncodedUrl('', 'mid', $this->mid), ENT_QUOTES, 'UTF-8'));
		}

		$this->_setMemberMenuHeaderContext();
		$this->setTemplateFile('member_received_reviews');
	}

	/**
	 * @brief 비즈니스 회원이 직접 작성한 회원평가(신청자 평가) 목록
	 */
	function dispPoomahhiMyMemberReviews()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return $this->stop('로그인이 필요합니다.');

		$oModel = getModel('poomahhi');
		$oMemberModel = getModel('member');

		$args = new stdClass();
		$args->reviewer_member_srl = $logged_info->member_srl;
		$args->page = Context::get('page') ?: 1;
		$args->list_count = $this->config->default_list_count;
		$review_output = $oModel->getMyWrittenMemberReviews($args);

		if($review_output->data)
		{
			foreach($review_output->data as &$row)
			{
				$target = $oMemberModel->getMemberInfoByMemberSrl($row->target_member_srl);
				$row->target_nickname = $target ? $target->nick_name : ($row->applicant_name ?: '');
				$row->target_profile_image = null;
				if($target && !empty($target->profile_image) && !empty($target->profile_image->src))
				{
					$row->target_profile_image = $target->profile_image->src;
				}
				$participant_review = $oModel->getReviewByApplication($row->application_srl);
				$row->cert_date = $participant_review ? $participant_review->regdate : null;
				$row->participant_review_srl = $participant_review ? (int)$participant_review->review_srl : 0;

				$row->has_replies = false;
				$row->review_replies = array();
				if($participant_review)
				{
					$replies = $oModel->getReviewReplies($participant_review->review_srl);
					if($replies)
					{
						$row->has_replies = count($replies) > 0;
						foreach($replies as &$rp)
						{
							$rp_member = $oMemberModel->getMemberInfoByMemberSrl($rp->member_srl);
							$rp->nick_name = $rp_member ? $rp_member->nick_name : '';
							$rp->profile_image = null;
							if($rp_member && !empty($rp_member->profile_image) && !empty($rp_member->profile_image->src))
							{
								$rp->profile_image = $rp_member->profile_image->src;
							}
							$rp_rpt_args = new stdClass();
							$rp_rpt_args->review_srl = (int)$rp->reply_srl;
							$rp_rpt_args->review_type = 'review_reply';
							$rp_rpt_args->reporter_member_srl = (int)$logged_info->member_srl;
							$rp_rpt_out = executeQuery('poomahhi.getReviewReport', $rp_rpt_args);
							$rp->is_reported = ($rp_rpt_out->toBool() && $rp_rpt_out->data) ? true : false;
						}
						unset($rp);
						$row->review_replies = $replies;
					}
				}

				if(isset($row->gender))
				{
					$row->gender_display = ($row->gender === 'male' || $row->gender === 'M') ? '남성' : (($row->gender === 'female' || $row->gender === 'F') ? '여성' : $row->gender);
				}
				else
				{
					$row->gender_display = '';
				}
				if(!empty($row->birth_date))
				{
					$row->birth_display = str_replace('-', '.', substr($row->birth_date, 0, 10));
				}
				else
				{
					$row->birth_display = '';
				}

				if(isset($row->product_type) && $row->product_type == 'local' && !empty($row->region_srl))
				{
					$region = $oModel->getRegion($row->region_srl);
					$row->region_title = $region ? $region->title : '';
				}
				else
				{
					$row->region_title = '';
				}

				$dday_src = !empty($row->apply_end_date) ? $row->apply_end_date : $row->deadline_date;
				if(!empty($dday_src))
				{
					$deadline_ts = strtotime(
						substr($dday_src, 0, 4) . '-' .
						substr($dday_src, 4, 2) . '-' .
						substr($dday_src, 6, 2)
					);
					$diff = ceil(($deadline_ts - strtotime(date('Y-m-d'))) / 86400);
					$row->dday = ($diff > 0) ? 'D-' . $diff : (($diff == 0) ? 'D-Day' : '마감');
				}
			}
			unset($row);
		}

		Context::set('review_list', $review_output->data ?: array());
		Context::set('total_count', $review_output->total_count);
		Context::set('total_page', $review_output->total_page);
		Context::set('page', (int)$args->page);
		Context::set('page_navigation', $review_output->page_navigation);

		Context::set('pmh_mm_reviews_lang_delete_confirm', htmlspecialchars(lang('poomahhi.my_reviews_comment_delete_confirm'), ENT_QUOTES, 'UTF-8'));
		Context::set('pmh_mm_reviews_lang_edit_empty', htmlspecialchars(lang('poomahhi.my_reviews_comment_edit_empty'), ENT_QUOTES, 'UTF-8'));
		Context::set('pmh_mm_reviews_mid_esc', htmlspecialchars((string)$this->mid, ENT_QUOTES, 'UTF-8'));

		$oController = getController('poomahhi');
		if($oController->_isBusinessMember($logged_info))
		{
			Context::set('use_business_layout', true);
			$this->_setBusinessCenterContext('reviews');
		}
		else
		{
			Context::set('use_business_layout', false);
			$this->_setMemberMenuHeaderContext();
		}
		$this->setTemplateFile('my_member_reviews');
	}

	/**
	 * @brief 관심 품앗이 목록
	 */
	function dispPoomahhiWishlist()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return $this->stop('로그인이 필요합니다.');

		$oModel = getModel('poomahhi');

		$args = new stdClass();
		$args->member_srl = $logged_info->member_srl;
		$args->page = Context::get('page') ?: 1;
		$args->list_count = $this->config->default_list_count;

		$wishlist_type = Context::get('type');
		if($wishlist_type === 'product' || $wishlist_type === 'local')
		{
			$args->wishlist_type = $wishlist_type;
		}

		$output = $oModel->getWishlist($args);

		$region_map = array();
		$region_list = $oModel->getRegionList($this->primary_module_srl);
		if($region_list)
		{
			foreach($region_list as $rg)
			{
				$region_map[$rg->region_srl] = $rg->title;
			}
		}

		if($output->data)
		{
			foreach($output->data as &$item)
			{
				$item->product = $oModel->getProduct($item->product_srl);
				if(!$item->product) continue;

				$product = $item->product;
				if($product->region_srl && isset($region_map[$product->region_srl]))
				{
					$product->region_title = $region_map[$product->region_srl];
				}
				$dday_source = $product->apply_end_date ?: $product->deadline_date;
				if($dday_source)
				{
					$days = $oModel->getProductApplyDeadlineDayOffset($dday_source);
					if($days !== null)
					{
						$product->dday = $days;
						$product->dday_text = $oModel->getProductApplyDdayTextFromDayOffset($days);
					}
				}
				if($product->short_description)
				{
					$product->content_summary = $product->short_description;
				}
				elseif($product->content)
				{
					$product->content_summary = mb_strimwidth(strip_tags($product->content), 0, 80, '...');
				}
			}
		}

		Context::set('wishlist', $output->data);
		Context::set('page_navigation', $output->page_navigation);
		Context::set('current_wishlist_type', $wishlist_type ?: 'all');

		$this->_setMemberMenuHeaderContext();
		$this->setTemplateFile('wishlist');
	}

	/**
	 * 비즈니스 센터 알림 목록 (ncenterlite 데이터·읽음 처리와 동일)
	 */
	function dispPoomahhiBusinessNotifications()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info)
		{
			return $this->stop('로그인이 필요합니다.');
		}

		$oController = getController('poomahhi');
		if(!$oController->_isBusinessMember($logged_info))
		{
			return $this->stop('권한이 없습니다.');
		}

		$oNcenterliteModel = getModel('ncenterlite');
		$oModel = getModel('poomahhi');
		if(!$oNcenterliteModel || !method_exists($oNcenterliteModel, 'getNotificationText'))
		{
			Context::set('ncenterlite_unavailable', true);
			Context::set('notification_list', array());
			Context::set('page_navigation', null);
			Context::set('total_count', 0);
			$tab = 'all';
			Context::set('notify_tab', $tab);
			Context::set('notify_unread_only', '');
			Context::set('notify_search_keyword', '');
			Context::set('bn_tab_all_class', 'pmh-bn-tab is-active');
			Context::set('bn_tab_poomahhi_class', 'pmh-bn-tab');
			Context::set('bn_tab_notice_class', 'pmh-bn-tab');
			$this->_setBusinessCenterContext('notifications');
			$this->setTemplateFile('business_notifications');
			return;
		}

		$tab = Context::get('notify_tab');
		if(!$tab || !in_array($tab, array('all', 'poomahhi', 'notice')))
		{
			$tab = 'all';
		}

		$search_raw = Context::get('search_keyword');
		$search_raw = is_string($search_raw) ? trim($search_raw) : '';
		$use_search = ($search_raw !== '');

		$unread_only = Context::get('unread_only');
		$unread_only = ($unread_only === 'Y' || $unread_only === '1' || $unread_only === 1);

		$page = max(1, (int)(Context::get('page') ?: 1));
		$list_count = (int)($this->config->default_list_count ?: 20);
		if($list_count < 1)
		{
			$list_count = 20;
		}
		$page_count = 10;

		$args = new stdClass();
		$args->member_srl = $logged_info->member_srl;
		$args->page = $page;
		$args->list_count = $list_count;
		$args->page_count = $page_count;

		if($unread_only)
		{
			$args->readed = 'N';
		}

		if($tab === 'all' && !$use_search)
		{
			$output = executeQueryArray('ncenterlite.getNotifyList', $args);
		}
		elseif($tab === 'all' && $use_search)
		{
			$escaped = str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $search_raw);
			$args->search_keyword = '%' . $escaped . '%';
			$output = executeQueryArray('poomahhi.getBusinessNotifyListSearchAll', $args);
		}
		else
		{
			if($tab === 'poomahhi')
			{
				$args->filter_type = 'X';
				$args->filter_url_like = '%poomahhi%';
			}
			elseif($tab === 'notice')
			{
				$args->filter_target_type = 'B';
			}
			if($use_search)
			{
				$escaped = str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $search_raw);
				$args->search_keyword = '%' . $escaped . '%';
				$output = executeQueryArray('poomahhi.getBusinessNotifyListSearch', $args);
			}
			else
			{
				$output = executeQueryArray('poomahhi.getBusinessNotifyList', $args);
			}
		}

		if(!$output->toBool())
		{
			$fail_msg = '';
			if(is_object($output))
			{
				if(method_exists($output, 'getMessage'))
				{
					$fail_msg = $output->getMessage();
				}
				elseif(isset($output->message))
				{
					$fail_msg = (string)$output->message;
				}
			}
			return $output;
		}

		$list = $output->data ?: array();
		if(!is_array($list))
		{
			$list = array($list);
		}
		$this->_enrichNcenterliteNotifyRows($list, $oNcenterliteModel);

		if($tab === 'poomahhi' && $list)
		{
			$list = array_values(array_filter($list, function($row) {
				if(!is_object($row))
				{
					return false;
				}
				if(isset($row->is_poomahhi_synthetic) && (string)$row->is_poomahhi_synthetic === 'deadline')
				{
					return true;
				}
				$tt = isset($row->target_type) ? (string)$row->target_type : '';
				return $tt !== 'B';
			}));
		}

		if(!$unread_only && !$use_search && ($tab === 'all' || $tab === 'poomahhi') && $page === 1)
		{
			$synth = $oModel->getDeadlineSyntheticRowsForBusinessNotifications($logged_info->member_srl, $this->mid);
			if($synth && count($synth) > 0)
			{
				$synth = array_slice($synth, 0, 15);
				foreach($list as $idx => $row)
				{
					if(!is_object($row))
					{
						continue;
					}
					$rd = isset($row->regdate) ? preg_replace('/[^0-9]/', '', (string)$row->regdate) : '';
					$rd = substr($rd . '00000000000000', 0, 14);
					$ts = DateTime::createFromFormat('YmdHis', $rd);
					if(!$ts && strlen($rd) >= 8)
					{
						$ts = DateTime::createFromFormat('Ymd', substr($rd, 0, 8));
					}
					if($ts)
					{
						$row->_pmh_merged_sort = (int)$ts->getTimestamp();
					}
					elseif(!empty($row->regdate))
					{
						$fb = @strtotime((string)$row->regdate);
						$row->_pmh_merged_sort = $fb ? (int)$fb : 0;
					}
					else
					{
						$row->_pmh_merged_sort = 0;
					}
					$list[$idx] = $row;
				}
				$list = array_merge($synth, $list);
				usort($list, function($a, $b) {
					$sa = is_object($a) && isset($a->_pmh_merged_sort) ? (int)$a->_pmh_merged_sort : 0;
					$sb = is_object($b) && isset($b->_pmh_merged_sort) ? (int)$b->_pmh_merged_sort : 0;
					return $sb <=> $sa;
				});
				$list = array_slice(array_values($list), 0, $list_count);
			}
		}

		Context::set('notification_list', $list);
		Context::set('page_navigation', $output->page_navigation);
		Context::set('total_count', $output->total_count);
		Context::set('notify_tab', $tab);
		Context::set('notify_unread_only', $unread_only ? 'Y' : '');
		Context::set('notify_search_keyword', $search_raw);
		Context::set('ncenterlite_unavailable', false);
		Context::set('bn_tab_all_class', $tab === 'all' ? 'pmh-bn-tab is-active' : 'pmh-bn-tab');
		Context::set('bn_tab_poomahhi_class', $tab === 'poomahhi' ? 'pmh-bn-tab is-active' : 'pmh-bn-tab');
		Context::set('bn_tab_notice_class', $tab === 'notice' ? 'pmh-bn-tab is-active' : 'pmh-bn-tab');

		$this->_setBusinessCenterContext('notifications');
		$this->setTemplateFile('business_notifications');
	}

	/**
	 * @brief 비즈니스 본인 정산 페이지
	 */
	function dispPoomahhiSettlement()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return $this->stop('로그인이 필요합니다.');

		$oController = getController('poomahhi');
		if(!$oController->_isBusinessMember($logged_info))
		{
			return $this->stop('권한이 없습니다.');
		}

		$oModel = getModel('poomahhi');

		$args = new stdClass();
		$args->member_srl = $logged_info->member_srl;
		$args->page = Context::get('page') ?: 1;
		$args->list_count = $this->config->default_list_count;

		$start_date = Context::get('start_date');
		$end_date = Context::get('end_date');
		if($start_date) $args->start_date = date('Ymd', strtotime($start_date)) . '000000';
		if($end_date) $args->end_date = date('Ymd', strtotime($end_date)) . '235959';

		$output = $oModel->getSettlementByBusiness($args);

		Context::set('settlement_list', $output->data);
		Context::set('page_navigation', $output->page_navigation);
		Context::set('start_date', $start_date);
		Context::set('end_date', $end_date);

		$this->_setBusinessCenterContext('settlement');
		$this->setTemplateFile('settlement');
	}

	/**
	 * @brief 개설자 비즈니스 홈 대시보드
	 */
	function dispPoomahhiCreatorDashboard()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return $this->stop('로그인이 필요합니다.');

		$oController = getController('poomahhi');
		if(!$oController->_isBusinessMember($logged_info))
		{
			return $this->stop('권한이 없습니다.');
		}

		$oModel = getModel('poomahhi');

		$member_srl = $logged_info->member_srl;

		$args_product = new stdClass();
		$args_product->member_srl = $member_srl;
		$args_product->list_count = 50;
		$args_product->page = 1;
		$product_output = $oModel->getProductList($args_product);
		$product_list = $product_output->data ?: array();
		if(!is_array($product_list))
		{
			$product_list = array($product_list);
		}

		$application_stats = $oModel->getApplicationStatsByOrganizer($member_srl);

		$args_recent_app = new stdClass();
		$args_recent_app->member_srl = $member_srl;
		$args_recent_app->list_count = 10;
		$args_recent_app->page = 1;
		$recent_app_output = $oModel->getRecentApplicationsByOrganizer($args_recent_app);
		$recent_applications = $recent_app_output->data ?: array();
		if(!is_array($recent_applications))
		{
			$recent_applications = array($recent_applications);
		}

		$lang_dash = (object)array(
			'dash_apply_rate_title' => '신청중인 품앗이 신청률 현황',
			'dash_cert_rate_title' => '진행중인 품앗이 인증 완료율',
			'dash_apply_rate_empty' => '신청 중인 품앗이가 없습니다.',
			'dash_cert_rate_empty' => '진행 중인 품앗이(선정자 있음)가 없습니다.',
		);
		$organizer_closed_count = 0;
		$organizer_ongoing_count = 0;
		foreach($product_list as $p)
		{
			if($p->status === 'closed')
			{
				$organizer_closed_count++;
			}
			else
			{
				$organizer_ongoing_count++;
			}
		}

		$organizer_apps_ongoing = (int)$application_stats->applied + (int)$application_stats->selected
			+ (int)$application_stats->under_review + (int)$application_stats->revision_requested;
		$organizer_apps_done = (int)$application_stats->completed + (int)$application_stats->rejected_cancelled;

		$applicant_status_counts = $oModel->getApplicationStatusCountsByMember($member_srl);
		$applicant_ongoing_count = (int)$applicant_status_counts->applied + (int)$applicant_status_counts->selected
			+ (int)$applicant_status_counts->under_review + (int)$applicant_status_counts->revision_requested;
		$applicant_done_count = (int)$applicant_status_counts->completed + (int)$applicant_status_counts->rejected_cancelled;

		$completed_product_count = $organizer_closed_count + $organizer_apps_done + $applicant_done_count;
		$active_product_count = $organizer_apps_ongoing + $applicant_ongoing_count;
		if($active_product_count < 1 && $organizer_ongoing_count > 0)
		{
			$active_product_count = $organizer_ongoing_count;
		}
		$dash_product_total = $completed_product_count + $active_product_count;
		$dash_donut_completed_pct = $dash_product_total > 0 ? (int)round(100 * $completed_product_count / $dash_product_total) : 0;
		$dash_donut_ongoing_pct = $dash_product_total > 0 ? max(0, 100 - $dash_donut_completed_pct) : 0;
		$dash_donut_completed_deg = $dash_product_total > 0 ? round(3.6 * $dash_donut_completed_pct, 3) : 0;

		$dash_total_applications = (int)$application_stats->applied + (int)$application_stats->selected
			+ (int)$application_stats->under_review + (int)$application_stats->revision_requested
			+ (int)$application_stats->completed + (int)$application_stats->rejected_cancelled;

		list($business_dashboard_banners, $has_new_application_banner, $new_banner_product_srl) = $this->_buildBusinessDashboardBanners(
			$member_srl,
			$product_list,
			$recent_applications,
			$oModel
		);

		$dash_apply_rate_items = array();
		$dash_cert_rate_items = array();
		foreach($product_list as $p)
		{
			if($p->status !== 'active')
			{
				continue;
			}
			$c = $oModel->getApplicationStatusCountsByProduct($p->product_srl);
			$max = (int)$p->max_applicants;
			$applied_cnt = (int)$c->applied + (int)$c->selected + (int)$c->under_review + (int)$c->revision_requested
				+ (int)$c->completed + (int)$c->rejected + (int)$c->cancelled;
			$pct_apply = ($max > 0) ? (int)min(100, round(100 * $applied_cnt / $max)) : 0;
			$sel = (int)$c->selected + (int)$c->under_review + (int)$c->revision_requested + (int)$c->completed;
			$comp = (int)$c->completed;
			$pct_cert = ($sel > 0) ? (int)min(100, round(100 * $comp / $sel)) : 0;
			$dash_apply_rate_items[] = (object)array(
				'product_srl' => $p->product_srl,
				'title' => $p->title,
				'pct' => $pct_apply,
				'current' => $applied_cnt,
				'max' => $max,
				'selection_date' => $p->selection_date,
			);
			if($sel > 0)
			{
				$dash_cert_rate_items[] = (object)array(
					'product_srl' => $p->product_srl,
					'title' => $p->title,
					'pct' => $pct_cert,
					'completed' => $comp,
					'selected' => $sel,
					'review_start_date' => $p->review_start_date,
					'review_end_date' => $p->review_end_date,
				);
			}
		}

		Context::set('completed_product_count', $completed_product_count);
		Context::set('active_product_count', $active_product_count);
		Context::set('dash_donut_completed_pct', $dash_donut_completed_pct);
		Context::set('dash_donut_ongoing_pct', $dash_donut_ongoing_pct);
		Context::set('dash_donut_completed_deg', $dash_donut_completed_deg);
		Context::set('dash_product_total', $dash_product_total);
		Context::set('dash_total_applications', $dash_total_applications);
		Context::set('dash_total_selected', (int)$application_stats->selected);
		Context::set('dash_inspection_pending', (int)$application_stats->under_review);
		Context::set('dash_revision_pending', (int)$application_stats->revision_requested);
		Context::set('business_dashboard_banners', $business_dashboard_banners);
		Context::set('has_new_application_banner', $has_new_application_banner);
		Context::set('new_banner_product_srl', $new_banner_product_srl);
		Context::set('dash_apply_rate_items', $dash_apply_rate_items);
		Context::set('dash_cert_rate_items', $dash_cert_rate_items);

		Context::set('lang_dash', $lang_dash);

		$this->_setBusinessCenterContext('home');
		$this->setTemplateFile('creator_dashboard');
	}

	/**
	 * @brief 품앗이 등록 유형 선택 (상품 / 지역)
	 */
	function dispPoomahhiProductWriteSelect()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return $this->stop('로그인이 필요합니다.');

		$oController = getController('poomahhi');
		if(!$oController->_isBusinessMember($logged_info) && !$oController->_isAdmin($logged_info))
		{
			return $this->stop('권한이 없습니다.');
		}

		$oModel = getModel('poomahhi');
		$args = new stdClass();
		$args->member_srl = $logged_info->member_srl;
		$args->list_count = 500;
		$args->page = 1;
		$output = $oModel->getProductList($args);
		$list = $output->data ?: array();
		if(!is_array($list))
		{
			$list = array($list);
		}

		$todayYmd = date('Ymd');
		$write_select_counts = array(
			'product' => array('active' => 0, 'closed' => 0),
			'local' => array('active' => 0, 'closed' => 0),
		);
		foreach($list as $p)
		{
			$key = ($p->product_type === 'local') ? 'local' : 'product';
			if($this->_isProductCompletedForWriteSelect($p, $todayYmd))
			{
				$write_select_counts[$key]['closed']++;
			}
			else
			{
				$write_select_counts[$key]['active']++;
			}
		}

		Context::set('wsc_product_active', $write_select_counts['product']['active']);
		Context::set('wsc_product_closed', $write_select_counts['product']['closed']);
		Context::set('wsc_local_active', $write_select_counts['local']['active']);
		Context::set('wsc_local_closed', $write_select_counts['local']['closed']);

		$pws_channel_list = $oModel->getChannelList($this->primary_module_srl);
		if($pws_channel_list && !is_array($pws_channel_list))
		{
			$pws_channel_list = array($pws_channel_list);
		}
		if(!$pws_channel_list)
		{
			$pws_channel_list = array();
		}
		$pws_region_list = $oModel->getRegionList($this->primary_module_srl);
		if($pws_region_list && !is_array($pws_region_list))
		{
			$pws_region_list = array($pws_region_list);
		}
		if(!$pws_region_list)
		{
			$pws_region_list = array();
		}
		Context::set('pws_channel_list', $pws_channel_list);
		Context::set('pws_region_list', $pws_region_list);

		$this->_setBusinessCenterContext('register');
		$this->setTemplateFile('product_write_select');
	}

	/**
	 * @brief 품앗이 관리 목록 (본인 등록 상품)
	 */
	function dispPoomahhiProductManageList()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return $this->stop('로그인이 필요합니다.');

		$oController = getController('poomahhi');
		if(!$oController->_isBusinessMember($logged_info) && !$oController->_isAdmin($logged_info))
		{
			return $this->stop('권한이 없습니다.');
		}

		$oModel = getModel('poomahhi');
		$member_srl = $logged_info->member_srl;
		$list_filter = Context::get('list_filter');
		$search_keyword = Context::get('search_keyword');
		$page = (int)(Context::get('page') ?: 1);
		if($page < 1) $page = 1;
		$list_count = (int)($this->config->default_list_count ?: 20);
		if($list_count < 1) $list_count = 20;

		$args = new stdClass();
		$args->member_srl = $member_srl;
		if($search_keyword) $args->search_keyword = '%' . $search_keyword . '%';
		$args->list_count = 2000;
		$args->page = 1;
		$product_output = $oModel->getProductList($args);
		$raw_list = $product_output->data ?: array();
		if(!is_array($raw_list))
		{
			$raw_list = array($raw_list);
		}

		$args_settlement = new stdClass();
		$args_settlement->member_srl = $member_srl;
		$args_settlement->list_count = 2000;
		$args_settlement->page = 1;
		$settlement_output = $oModel->getProductApplicationStatsByMember($args_settlement);
		$settlement_list = $settlement_output->data ?: array();
		if(!is_array($settlement_list))
		{
			$settlement_list = array($settlement_list);
		}
		$settlement_by_product = array();
		foreach($settlement_list as $s)
		{
			$settlement_by_product[$s->product_srl] = $s;
		}

		$todayYmd = date('Ymd');
		$filtered = array();
		foreach($raw_list as $p)
		{
			if(!isset($settlement_by_product[$p->product_srl]))
			{
				$empty = new stdClass();
				$empty->product_srl = $p->product_srl;
				$empty->total_applications = 0;
				$empty->selected_count = 0;
				$empty->completed_count = 0;
				$settlement_by_product[$p->product_srl] = $empty;
			}
			$p->settlement = $settlement_by_product[$p->product_srl];
			$p->manage_phase = $this->_getProductManagePhase($p, $todayYmd);
			if($list_filter && $p->manage_phase !== $list_filter)
			{
				continue;
			}
			$filtered[] = $p;
		}

		$total_count = count($filtered);
		$total_page = max(1, (int)ceil($total_count / $list_count));
		if($page > $total_page) $page = $total_page;
		$offset = ($page - 1) * $list_count;
		$paged_list = array_slice($filtered, $offset, $list_count);

		$region_list = $oModel->getRegionList($this->primary_module_srl);
		$region_map = array();
		if($region_list)
		{
			$rl = is_array($region_list) ? $region_list : array($region_list);
			foreach($rl as $rg)
			{
				$region_map[$rg->region_srl] = $rg->title;
			}
		}
		foreach($paged_list as $p)
		{
			if($p->product_type === 'local' && !empty($p->region_srl) && isset($region_map[$p->region_srl]))
			{
				$p->list_tag_label = $region_map[$p->region_srl];
			}
			else
			{
				$p->list_tag_label = $p->shopping_channel ? $p->shopping_channel : '';
			}
			if(!empty($p->short_description))
			{
				$p->list_summary = $p->short_description;
			}
			elseif(!empty($p->content))
			{
				$p->list_summary = mb_strimwidth(strip_tags($p->content), 0, 120, '...', 'UTF-8');
			}
			else
			{
				$p->list_summary = '';
			}
		}

		$page_navigation = new stdClass();
		$page_navigation->first_page = 1;
		$page_navigation->last_page = $total_page;
		$page_navigation->cur_page = $page;
		$page_navigation->total_page = $total_page;

		Context::set('product_manage_list', $paged_list);
		Context::set('page_navigation', $page_navigation);
		Context::set('total_count', $total_count);
		Context::set('list_filter', $list_filter ?: '');
		Context::set('search_keyword', $search_keyword ?: '');
		Context::set('current_manage_page', $page);
		Context::set('page', $page);

		$this->_setBusinessCenterContext('manage');
		$this->setTemplateFile('product_manage_list');
	}

	/**
	 * @brief 회원가입 유형 선택 페이지 (일반회원 / 비즈니스)
	 */
	function dispPoomahhiSignupSelect()
	{
		$oModuleModel = getModel('module');
		$member_mid = isset($this->config->signup_member_mid) && $this->config->signup_member_mid
			? $this->config->signup_member_mid
			: 'member';
		$member_module = $oModuleModel->getModuleInfoByMid($member_mid);
		if(!$member_module || $member_module->module !== 'member')
		{
			$mid_list = $oModuleModel->getMidList(null, array('mid', 'module'));
			foreach($mid_list as $m)
			{
				if($m->module === 'member')
				{
					$member_mid = $m->mid;
					break;
				}
			}
		}
		Context::set('signup_member_mid', $member_mid);
		$this->setTemplateFile('signup_select');
	}
}
