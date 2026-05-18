<?php
/**
 * @class poomahhiAdminView
 * @author WP
 * @brief 품앗이 모듈 관리자 뷰
 */
class poomahhiAdminView extends poomahhi
{
	function init()
	{
		$this->setTemplatePath($this->module_path . 'tpl/');

		$oModuleModel = getModel('module');
		$this->module_info = $oModuleModel->getModuleInfoByMid('poomahhi');
		if($this->module_info)
		{
			Context::set('module_info', $this->module_info);
			Context::set('module_srl', $this->module_info->module_srl);
		}
	}

	/**
	 * @brief 모듈 설정 페이지
	 */
	function dispPoomahhiAdminConfig()
	{
		$oModel = getModel('poomahhi');
		$config = $oModel->getModuleConfig();

		// 회원 그룹 목록
		$oMemberModel = getModel('member');
		$group_list = $oMemberModel->getGroups();

		// 스킨 목록
		$oModuleModel = getModel('module');
		$skin_list = $oModuleModel->getSkins($this->module_path);
		if(!is_array($skin_list)) $skin_list = array();
		$mskin_list = $skin_list;

		// 레이아웃 목록
		$oLayoutModel = getModel('layout');
		$layout_list = $oLayoutModel->getLayoutList();
		if(!is_array($layout_list)) $layout_list = array();

		Context::set('config', $config);
		Context::set('group_list', $group_list);
		Context::set('skin_list', $skin_list);
		Context::set('mskin_list', $mskin_list);
		Context::set('layout_list', $layout_list);
		Context::set('module_info', $this->module_info);

		$this->setTemplateFile('config');
	}

	/**
	 * @brief 비즈니스 회원 전체 알림(ncenterlite) 발송
	 */
	function dispPoomahhiAdminBusinessBroadcast()
	{
		$oModel = getModel('poomahhi');
		$config = $oModel->getModuleConfig();
		Context::set('config', $config);

		$this->setTemplateFile('business_broadcast');
	}

	/**
	 * @brief 카테고리 관리
	 */
	function dispPoomahhiAdminCategoryList()
	{
		if(!$this->module_info || !$this->module_info->module_srl)
		{
			throw new Rhymix\Framework\Exceptions\TargetNotFound('모듈을 찾을 수 없습니다.');
		}
		$oDocumentModel = getModel('document');
		$category_content = $oDocumentModel->getCategoryHTML($this->module_info->module_srl);
		Context::set('category_content', $category_content);

		$this->setTemplateFile('category_list');
	}

	/**
	 * @brief 쇼핑채널 관리
	 */
	function dispPoomahhiAdminChannelList()
	{
		if(!$this->module_info || !$this->module_info->module_srl)
		{
			throw new Rhymix\Framework\Exceptions\TargetNotFound('모듈을 찾을 수 없습니다.');
		}
		$oModel = getModel('poomahhi');
		$channel_list = $oModel->getChannelList($this->module_info->module_srl);

		$channel_srl = Context::get('channel_srl');
		$channel = null;
		if($channel_srl) $channel = $oModel->getChannel($channel_srl);

		Context::set('channel_list', $channel_list);
		Context::set('channel', $channel);

		$this->setTemplateFile('channel_list');
	}

	/**
	 * @brief 지역 관리
	 */
	function dispPoomahhiAdminRegionList()
	{
		if(!$this->module_info || !$this->module_info->module_srl)
		{
			throw new Rhymix\Framework\Exceptions\TargetNotFound('모듈을 찾을 수 없습니다.');
		}
		$oModel = getModel('poomahhi');
		$region_list = $oModel->getRegionList($this->module_info->module_srl);

		$region_srl = Context::get('region_srl');
		$region = null;
		if($region_srl) $region = $oModel->getRegion($region_srl);

		Context::set('region_list', $region_list);
		Context::set('region', $region);

		$this->setTemplateFile('region_list');
	}

	/**
	 * @brief 확장변수 템플릿 관리
	 */
	function dispPoomahhiAdminExtraTemplate()
	{
		if(!$this->module_info || !$this->module_info->module_srl)
		{
			throw new Rhymix\Framework\Exceptions\TargetNotFound('모듈을 찾을 수 없습니다.');
		}
		$oModel = getModel('poomahhi');
		$template_list = $oModel->getExtraTemplateList($this->module_info->module_srl);

		$template_srl = Context::get('template_srl');
		$template = null;
		if($template_srl) $template = $oModel->getExtraTemplate($template_srl);

		// 각 템플릿에 확장변수 개수 추가
		if($template_list)
		{
			foreach($template_list as &$tpl)
			{
				$def_args = new stdClass();
				$def_args->template_srl = $tpl->template_srl;
				$def_output = executeQueryArray('poomahhi.getExtraDefList', $def_args);
				$tpl->extra_def_count = ($def_output->data) ? count($def_output->data) : 0;
			}
		}

		Context::set('template_list', $template_list);
		Context::set('template', $template);

		$this->setTemplateFile('extra_template');
	}

	/**
	 * @brief 확장변수 정의 관리 (템플릿별)
	 */
	function dispPoomahhiAdminExtraDef()
	{
		$oModel = getModel('poomahhi');

		// 템플릿 SRL (필수)
		$template_srl = Context::get('template_srl');

		// 템플릿 목록 (드롭다운용)
		$template_list = array();
		if($this->module_info && $this->module_info->module_srl)
		{
			$template_list = $oModel->getExtraTemplateList($this->module_info->module_srl);
		}

		// 선택된 템플릿의 확장변수 목록
		$extra_def_list = array();
		if($template_srl)
		{
			$args = new stdClass();
			$args->template_srl = $template_srl;
			$output = executeQueryArray('poomahhi.getExtraDefList', $args);
			$extra_def_list = ($output->toBool() && $output->data) ? $output->data : array();
		}

		// 수정 모드
		$extra_def_srl = Context::get('extra_def_srl');
		$extra_def = null;
		if($extra_def_srl)
		{
			$extra_def = $oModel->getExtraDef($extra_def_srl);
		}

		Context::set('template_list', $template_list);
		Context::set('template_srl', $template_srl);
		Context::set('extra_def_list', $extra_def_list);
		Context::set('extra_def', $extra_def);

		$this->setTemplateFile('extra_def');
	}

	/**
	 * @brief 상품 관리
	 */
	function dispPoomahhiAdminProductList()
	{
		$oModel = getModel('poomahhi');

		$args = new stdClass();
		$args->page = Context::get('page') ?: 1;
		$args->list_count = 20;

		$status = Context::get('status');
		if($status) $args->status = $status;

		$search_keyword = Context::get('search_keyword');
		if($search_keyword) $args->search_keyword = '%' . $search_keyword . '%';

		$output = $oModel->getProductList($args);

		Context::set('product_list', $output->data);
		Context::set('page_navigation', $output->page_navigation);
		Context::set('total_count', $output->total_count);

		$this->setTemplateFile('product_list');
	}

	/**
	 * @brief 신청 관리
	 */
	function dispPoomahhiAdminApplicationList()
	{
		$oModel = getModel('poomahhi');

		$product_srl = Context::get('product_srl');

		$args = new stdClass();
		$args->page = Context::get('page') ?: 1;
		$args->list_count = 20;
		if($product_srl) $args->product_srl = $product_srl;

		$status = Context::get('status');
		if($status) $args->status = $status;

		$output = $oModel->getApplicationList($args);

		// 각 신청건에 상품/회원 정보 추가
		$oMemberModel = getModel('member');
		if($output->data)
		{
			foreach($output->data as &$app)
			{
				$app->product = $oModel->getProduct($app->product_srl);
				$app->member_info = $oMemberModel->getMemberInfoByMemberSrl($app->member_srl);
				$app->review = $oModel->getReviewByApplication($app->application_srl);
			}
		}

		Context::set('application_list', $output->data);
		Context::set('page_navigation', $output->page_navigation);
		Context::set('total_count', $output->total_count);
		Context::set('product_srl', $product_srl);

		$admin_list_url = getUrl('', 'module', 'admin', 'act', 'dispPoomahhiAdminApplicationList');
		if($product_srl) $admin_list_url = getUrl('', 'module', 'admin', 'act', 'dispPoomahhiAdminApplicationList', 'product_srl', $product_srl);
		Context::set('admin_application_list_url', $admin_list_url);

		$this->setTemplateFile('application_list');
	}

	/**
	 * @brief 전체 비즈니스별 정산
	 */
	function dispPoomahhiAdminSettlement()
	{
		$oModel = getModel('poomahhi');

		$args = new stdClass();
		$args->page = Context::get('page') ?: 1;
		$args->list_count = 20;

		$start_date = Context::get('start_date');
		$end_date = Context::get('end_date');
		if($start_date) $args->start_date = date('Ymd', strtotime($start_date)) . '000000';
		if($end_date) $args->end_date = date('Ymd', strtotime($end_date)) . '235959';

		$output = $oModel->getSettlementAllBusiness($args);

		// 회원 정보 추가
		$oMemberModel = getModel('member');
		if($output->data)
		{
			foreach($output->data as &$item)
			{
				$item->member_info = $oMemberModel->getMemberInfoByMemberSrl($item->business_member_srl);
			}
		}

		Context::set('settlement_list', $output->data);
		Context::set('page_navigation', $output->page_navigation);
		Context::set('start_date', $start_date);
		Context::set('end_date', $end_date);

		$this->setTemplateFile('settlement');
	}

	/**
	 * @brief 신고 관리
	 */
	function dispPoomahhiAdminReportList()
	{
		$oModel = getModel('poomahhi');
		$oMemberModel = getModel('member');

		$args = new stdClass();
		$args->page = Context::get('page') ?: 1;
		$args->list_count = 20;

		$review_type = Context::get('review_type');
		if($review_type) $args->review_type = $review_type;

		$output = executeQueryArray('poomahhi.getReviewReportList', $args);

		if($output->data)
		{
			foreach($output->data as &$report)
			{
				$report->reporter_info = $oMemberModel->getMemberInfoByMemberSrl($report->reporter_member_srl);

				$report->review_content = '';
				$report->reviewed_member_info = null;
				if($report->review_type === 'member_review')
				{
					$mr = $oModel->getMemberReview($report->review_srl);
					if($mr)
					{
						$report->review_content = $mr->content;
						$report->reviewed_member_info = $oMemberModel->getMemberInfoByMemberSrl($mr->reviewer_member_srl);
					}
				}
				elseif($report->review_type === 'review_reply')
				{
					$reply = $oModel->getReviewReply($report->review_srl);
					if($reply)
					{
						$report->review_content = $reply->content;
						$report->reviewed_member_info = $oMemberModel->getMemberInfoByMemberSrl($reply->member_srl);
					}
				}
				else
				{
					$review = $oModel->getReview($report->review_srl);
					if($review)
					{
						$report->review_content = $review->content;
						$report->reviewed_member_info = $oMemberModel->getMemberInfoByMemberSrl($review->member_srl);
					}
				}
			}
			unset($report);
		}

		Context::set('report_list', $output->data ?: array());
		Context::set('page_navigation', $output->page_navigation);
		Context::set('total_count', $output->total_count);
		Context::set('current_review_type', $review_type);

		$this->setTemplateFile('report_list');
	}
}
