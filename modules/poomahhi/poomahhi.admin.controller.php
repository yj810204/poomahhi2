<?php
/**
 * @class poomahhiAdminController
 * @author WP
 * @brief 품앗이 모듈 관리자 컨트롤러
 */
class poomahhiAdminController extends poomahhi
{
	function init()
	{
	}

	/**
	 * @brief 모듈 설정 저장
	 */
	function procPoomahhiAdminSaveConfig()
	{
		$oModuleController = getController('module');
		$oModuleModel = getModel('module');

		$args = Context::getRequestVars();

		// 1) 모듈 인스턴스 업데이트 (레이아웃·스킨)
		// 주의: $args에는 form의 module=admin, act 등이 포함되어 있으므로
		// updateModule에 전체 $args를 넘기면 module 필드가 'admin'으로 오염됨
		// 반드시 필요한 필드만 별도 객체로 구성해서 전달
		$module_info = $oModuleModel->getModuleInfoByMid('poomahhi');
		if($module_info && $module_info->module_srl)
		{
			$update_args = new stdClass();
			$update_args->module_srl = $module_info->module_srl;
			$update_args->module = 'poomahhi';
			$update_args->mid = 'poomahhi';
			$update_args->skin = $args->skin ?: 'default';
			$update_args->mskin = $args->mskin ?: '';
			$update_args->layout_srl = (int)$args->layout_srl;
			$update_args->browser_title = $module_info->browser_title;
			$output = $oModuleController->updateModule($update_args);
			if(!$output->toBool()) return $output;
		}

		// 2) 모듈 커스텀 설정 저장
		$config = new stdClass();
		$config->max_certification_images = (int)$args->max_certification_images ?: 10;
		$config->max_purchase_images = (int)$args->max_purchase_images ?: 10;
		$config->review_deadline_days = (int)$args->review_deadline_days ?: 14;
		$config->default_list_count = (int)$args->default_list_count ?: 20;
		$config->general_group_srl = $args->general_group_srl ?: '';
		$config->business_group_srl = $args->business_group_srl ?: '';
		$config->signup_select_mid = isset($args->signup_select_mid) ? trim((string)$args->signup_select_mid) : '';
		$config->own_document_product_mid = isset($args->own_document_product_mid) ? trim((string)$args->own_document_product_mid) : '';
		$config->own_document_region_mid = isset($args->own_document_region_mid) ? trim((string)$args->own_document_region_mid) : '';
		$config->privacy_content = $args->privacy_content ?: '';
		$config->business_home_deadline_days = (int)$args->business_home_deadline_days ?: 7;
		if($config->business_home_deadline_days < 1) $config->business_home_deadline_days = 7;
		if($config->business_home_deadline_days > 90) $config->business_home_deadline_days = 90;
		$config->noti_tpl_new_application = isset($args->noti_tpl_new_application) ? trim((string)$args->noti_tpl_new_application) : '';
		$config->noti_tpl_review_submitted = isset($args->noti_tpl_review_submitted) ? trim((string)$args->noti_tpl_review_submitted) : '';
		$config->noti_tpl_revision_requested = isset($args->noti_tpl_revision_requested) ? trim((string)$args->noti_tpl_revision_requested) : '';
		$config->noti_tpl_deadline_banner = isset($args->noti_tpl_deadline_banner) ? trim((string)$args->noti_tpl_deadline_banner) : '';
		if(strlen($config->noti_tpl_new_application) > 500) $config->noti_tpl_new_application = function_exists('mb_substr') ? mb_substr($config->noti_tpl_new_application, 0, 500, 'UTF-8') : substr($config->noti_tpl_new_application, 0, 500);
		if(strlen($config->noti_tpl_review_submitted) > 500) $config->noti_tpl_review_submitted = function_exists('mb_substr') ? mb_substr($config->noti_tpl_review_submitted, 0, 500, 'UTF-8') : substr($config->noti_tpl_review_submitted, 0, 500);
		if(strlen($config->noti_tpl_revision_requested) > 500) $config->noti_tpl_revision_requested = function_exists('mb_substr') ? mb_substr($config->noti_tpl_revision_requested, 0, 500, 'UTF-8') : substr($config->noti_tpl_revision_requested, 0, 500);
		if(strlen($config->noti_tpl_deadline_banner) > 500) $config->noti_tpl_deadline_banner = function_exists('mb_substr') ? mb_substr($config->noti_tpl_deadline_banner, 0, 500, 'UTF-8') : substr($config->noti_tpl_deadline_banner, 0, 500);
		$config->content_point_type = 'rhymix';

		$output = $oModuleController->insertModuleConfig('poomahhi', $config);
		if(!$output->toBool()) return $output;

		$this->setMessage('수정되었습니다.');
		$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispPoomahhiAdminConfig');
		$this->setRedirectUrl($returnUrl);
	}

	/**
	 * @brief 쇼핑채널 저장 (추가/수정)
	 */
	function procPoomahhiAdminSaveChannel()
	{
		$args = Context::getRequestVars();
		if(!$args->title) return new BaseObject(-1, '잘못된 요청입니다.');

		$oModuleModel = getModel('module');
		$module_info = $oModuleModel->getModuleInfoByMid('poomahhi');
		if(!$module_info || !$module_info->module_srl) return new BaseObject(-1, '모듈을 찾을 수 없습니다.');
		$args->module_srl = $module_info->module_srl;

		if($args->channel_srl)
		{
			$output = executeQuery('poomahhi.updateChannel', $args);
		}
		else
		{
			$args->channel_srl = getNextSequence();
			// 새 항목은 목록 맨 끝에 추가
			$oModel = getModel('poomahhi');
			$existing = $oModel->getChannelList($args->module_srl);
			$args->list_order = count($existing);
			$output = executeQuery('poomahhi.insertChannel', $args);
		}

		if(!$output->toBool()) return $output;

		$this->setMessage('수정되었습니다.');
		$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispPoomahhiAdminChannelList');
		$this->setRedirectUrl($returnUrl);
	}

	/**
	 * @brief 쇼핑채널 정렬 저장 (Drag & Drop)
	 */
	function procPoomahhiAdminSortChannels()
	{
		$channel_srls = Context::get('channel_srls');
		if(!$channel_srls) return new BaseObject(-1, '잘못된 요청입니다.');

		$srl_list = explode(',', $channel_srls);
		foreach($srl_list as $order => $channel_srl)
		{
			$channel_srl = (int)trim($channel_srl);
			if(!$channel_srl) continue;
			$args = new stdClass();
			$args->channel_srl = $channel_srl;
			$args->list_order = $order;
			executeQuery('poomahhi.updateChannelOrder', $args);
		}

		$this->setMessage('수정되었습니다.');
	}

	/**
	 * @brief 쇼핑채널 삭제
	 */
	function procPoomahhiAdminDeleteChannel()
	{
		$channel_srl = Context::get('channel_srl');
		if(!$channel_srl) return new BaseObject(-1, '잘못된 요청입니다.');

		$output = executeQuery('poomahhi.deleteChannel', (object)array('channel_srl' => $channel_srl));
		if(!$output->toBool()) return $output;

		$this->setMessage('삭제되었습니다.');
		$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispPoomahhiAdminChannelList');
		$this->setRedirectUrl($returnUrl);
	}

	/**
	 * @brief 지역 저장 (추가/수정)
	 */
	function procPoomahhiAdminSaveRegion()
	{
		$args = Context::getRequestVars();
		if(!$args->title) return new BaseObject(-1, '잘못된 요청입니다.');

		$oModuleModel = getModel('module');
		$module_info = $oModuleModel->getModuleInfoByMid('poomahhi');
		if(!$module_info || !$module_info->module_srl) return new BaseObject(-1, '모듈을 찾을 수 없습니다.');
		$args->module_srl = $module_info->module_srl;

		if($args->region_srl)
		{
			$output = executeQuery('poomahhi.updateRegion', $args);
		}
		else
		{
			$args->region_srl = getNextSequence();
			$oModel = getModel('poomahhi');
			$existing = $oModel->getRegionList($args->module_srl);
			$args->list_order = count($existing);
			$output = executeQuery('poomahhi.insertRegion', $args);
		}

		if(!$output->toBool()) return $output;

		$this->setMessage('수정되었습니다.');
		$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispPoomahhiAdminRegionList');
		$this->setRedirectUrl($returnUrl);
	}

	/**
	 * @brief 지역 정렬 저장 (Drag & Drop)
	 */
	function procPoomahhiAdminSortRegions()
	{
		$region_srls = Context::get('region_srls');
		if(!$region_srls) return new BaseObject(-1, '잘못된 요청입니다.');

		$srl_list = explode(',', $region_srls);
		foreach($srl_list as $order => $region_srl)
		{
			$region_srl = (int)trim($region_srl);
			if(!$region_srl) continue;
			$args = new stdClass();
			$args->region_srl = $region_srl;
			$args->list_order = $order;
			executeQuery('poomahhi.updateRegionOrder', $args);
		}

		$this->setMessage('수정되었습니다.');
	}

	/**
	 * @brief 지역 삭제
	 */
	function procPoomahhiAdminDeleteRegion()
	{
		$region_srl = Context::get('region_srl');
		if(!$region_srl) return new BaseObject(-1, '잘못된 요청입니다.');

		$output = executeQuery('poomahhi.deleteRegion', (object)array('region_srl' => $region_srl));
		if(!$output->toBool()) return $output;

		$this->setMessage('삭제되었습니다.');
		$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispPoomahhiAdminRegionList');
		$this->setRedirectUrl($returnUrl);
	}

	/**
	 * @brief 확장변수 템플릿 저장 (추가/수정)
	 */
	function procPoomahhiAdminSaveExtraTemplate()
	{
		$args = Context::getRequestVars();
		if(!$args->title) return new BaseObject(-1, '잘못된 요청입니다.');

		$oModuleModel = getModel('module');
		$module_info = $oModuleModel->getModuleInfoByMid('poomahhi');
		if(!$module_info || !$module_info->module_srl) return new BaseObject(-1, '모듈을 찾을 수 없습니다.');
		$args->module_srl = $module_info->module_srl;

		if($args->template_srl)
		{
			$output = executeQuery('poomahhi.updateExtraTemplate', $args);
		}
		else
		{
			$args->template_srl = getNextSequence();
			$oModel = getModel('poomahhi');
			$existing = $oModel->getExtraTemplateList($args->module_srl);
			$args->list_order = count($existing);
			$output = executeQuery('poomahhi.insertExtraTemplate', $args);
		}

		if(!$output->toBool()) return $output;

		$this->setMessage('수정되었습니다.');
		$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispPoomahhiAdminExtraTemplate');
		$this->setRedirectUrl($returnUrl);
	}

	/**
	 * @brief 확장변수 템플릿 삭제
	 */
	function procPoomahhiAdminDeleteExtraTemplate()
	{
		$template_srl = Context::get('template_srl');
		if(!$template_srl) return new BaseObject(-1, '잘못된 요청입니다.');

		// 템플릿에 속한 확장변수도 함께 삭제
		executeQuery('poomahhi.deleteExtraDefsByTemplate', (object)array('template_srl' => $template_srl));

		$output = executeQuery('poomahhi.deleteExtraTemplate', (object)array('template_srl' => $template_srl));
		if(!$output->toBool()) return $output;

		$this->setMessage('삭제되었습니다.');
		$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispPoomahhiAdminExtraTemplate');
		$this->setRedirectUrl($returnUrl);
	}

	/**
	 * @brief 확장변수 템플릿 정렬 (Drag & Drop)
	 */
	function procPoomahhiAdminSortExtraTemplates()
	{
		$template_srls = Context::get('template_srls');
		if(!$template_srls) return new BaseObject(-1, '잘못된 요청입니다.');

		$srl_list = explode(',', $template_srls);
		foreach($srl_list as $order => $template_srl)
		{
			$template_srl = (int)trim($template_srl);
			if(!$template_srl) continue;
			$args = new stdClass();
			$args->template_srl = $template_srl;
			$args->list_order = $order;
			executeQuery('poomahhi.updateExtraTemplateOrder', $args);
		}

		$this->setMessage('수정되었습니다.');
	}

	/**
	 * @brief 확장변수 정의 저장 (추가/수정)
	 */
	function procPoomahhiAdminSaveExtraDef()
	{
		$args = Context::getRequestVars();

		if($args->extra_def_srl)
		{
			// 수정
			$output = executeQuery('poomahhi.updateExtraDef', $args);
		}
		else
		{
			// 추가
			$args->extra_def_srl = getNextSequence();
			if(!$args->module_srl) $args->module_srl = 0;
			if(!$args->template_srl) $args->template_srl = 0;
			// 새 항목은 목록 맨 끝에 추가
			$oModel = getModel('poomahhi');
			$existing_args = new stdClass();
			$existing_args->template_srl = $args->template_srl;
			$existing_output = executeQueryArray('poomahhi.getExtraDefList', $existing_args);
			$args->list_order = ($existing_output->data) ? count($existing_output->data) : 0;
			$output = executeQuery('poomahhi.insertExtraDef', $args);
		}

		if(!$output->toBool()) return $output;

		$this->setMessage('수정되었습니다.');
		$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispPoomahhiAdminExtraDef', 'template_srl', $args->template_srl);
		$this->setRedirectUrl($returnUrl);
	}

	/**
	 * @brief 확장변수 정의 정렬 저장 (Drag & Drop)
	 */
	function procPoomahhiAdminSortExtraDefs()
	{
		$extra_def_srls = Context::get('extra_def_srls');
		if(!$extra_def_srls) return new BaseObject(-1, '잘못된 요청입니다.');

		$srl_list = explode(',', $extra_def_srls);
		foreach($srl_list as $order => $extra_def_srl)
		{
			$extra_def_srl = (int)trim($extra_def_srl);
			if(!$extra_def_srl) continue;
			$args = new stdClass();
			$args->extra_def_srl = $extra_def_srl;
			$args->list_order = $order;
			executeQuery('poomahhi.updateExtraDefOrder', $args);
		}

		$this->setMessage('수정되었습니다.');
	}

	/**
	 * @brief 확장변수 정의 삭제
	 */
	function procPoomahhiAdminDeleteExtraDef()
	{
		$extra_def_srl = Context::get('extra_def_srl');
		$template_srl = Context::get('template_srl');
		if(!$extra_def_srl) return new BaseObject(-1, '잘못된 요청입니다.');

		$output = executeQuery('poomahhi.deleteExtraDef', (object)array('extra_def_srl' => $extra_def_srl));
		if(!$output->toBool()) return $output;

		$this->setMessage('삭제되었습니다.');
		$returnUrl = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispPoomahhiAdminExtraDef', 'template_srl', $template_srl);
		$this->setRedirectUrl($returnUrl);
	}

	/**
	 * @brief 회원 확장변수 member_type=business 인 승인 회원에게 ncenterlite 공지 알림 발송 (target_type B, type X)
	 */
	function procPoomahhiAdminSendBusinessBroadcast()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info)
		{
			return new BaseObject(-1, '로그인이 필요합니다.');
		}

		$message = trim((string)Context::get('broadcast_message'));
		if($message === '')
		{
			return new BaseObject(-1, '알림 내용을 입력해 주세요.');
		}

		$oTplModel = getModel('poomahhi');
		$message = $oTplModel->replacePoomahhiNotificationTemplate($message, array(
			'date' => date('Y-m-d H:i'),
			'admin_nick' => isset($logged_info->nick_name) ? (string)$logged_info->nick_name : '',
			'admin_user_id' => isset($logged_info->user_id) ? (string)$logged_info->user_id : '',
		));

		$url = trim((string)Context::get('broadcast_url'));

		$oNc = getController('ncenterlite');
		if(!$oNc || !method_exists($oNc, '_insertNotify'))
		{
			return new BaseObject(-1, '알림 센터(ncenterlite) 모듈을 사용할 수 없습니다.');
		}

		$oModuleModel = getModel('module');
		$biz_mid = 'poomahhi_business';
		$mid_list = $oModuleModel->getMidList(null, array('mid', 'module'));
		if($mid_list)
		{
			foreach($mid_list as $m)
			{
				if(isset($m->module) && $m->module === 'poomahhi' && isset($m->mid) && $m->mid === 'poomahhi_business')
				{
					$biz_mid = $m->mid;
					break;
				}
			}
		}
		if($url === '')
		{
			$url = getNotEncodedUrl('', 'mid', $biz_mid, 'act', 'dispPoomahhiBusinessNotifications', 'notify_tab', 'notice');
		}

		// Rhymix: 회원 확장변수는 member.extra_vars 에 PHP serialize 로 저장됨 (member_extra_vars 테이블 아님)
		$qargs = new stdClass();
		$qargs->member_denied = 'N';
		$qargs->member_status = 'APPROVED';
		$qargs->member_type_business_pattern = '%s:11:"member_type";s:8:"business"%';
		$qargs->list_count = 50000;
		$qargs->page = 1;
		$out = executeQueryArray('poomahhi.getMemberSrlsByMemberTypeBusiness', $qargs);
		if(!$out->toBool())
		{
			return $out;
		}
		$rows = $out->data ?: array();
		if(!is_array($rows))
		{
			$rows = array($rows);
		}
		if(!count($rows))
		{
			$this->setMessage('확장변수 member_type이 business이며 승인된 회원이 없습니다.');
			$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispPoomahhiAdminBusinessBroadcast'));
			return new BaseObject();
		}

		if(function_exists('set_time_limit'))
		{
			@set_time_limit(600);
		}

		$sent = 0;
		$failed = 0;
		foreach($rows as $row)
		{
			$member_srl = (int)$row->member_srl;
			if($member_srl < 1)
			{
				continue;
			}

			$nargs = new stdClass();
			$nargs->config_type = 'custom';
			$nargs->module_srl = 0;
			$nargs->member_srl = $member_srl;
			$nargs->type = 'X';
			$nargs->target_type = 'B';
			$nargs->srl = 0;
			$nargs->target_srl = 0;
			$nargs->target_p_srl = 0;
			// sendNotification과 동일: 수신=member_srl, 발신(표시)=target_member_srl
			$nargs->target_member_srl = (int)$logged_info->member_srl;
			$nargs->target_url = $url;
			$nargs->target_body = $message;
			if(function_exists('mb_substr'))
			{
				$nargs->target_summary = mb_substr($message, 0, 200, 'UTF-8');
			}
			else
			{
				$nargs->target_summary = substr($message, 0, 200);
			}

			$nout = $oNc->_insertNotify($nargs);
			if($nout->toBool())
			{
				$sent++;
			}
			else
			{
				$failed++;
			}
		}

		$this->setMessage(sprintf('알림 발송 완료: 성공 %d건, 실패 %d건', $sent, $failed));
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispPoomahhiAdminBusinessBroadcast'));
		return new BaseObject();
	}
}
