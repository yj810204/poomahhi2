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
}
