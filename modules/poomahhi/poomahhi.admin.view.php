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
	 * @brief 관리자 목록 순번 (페이지네이션, 전체 건수 기준 내림차순)
	 */
	protected function assignAdminListNumbersDescending(&$list, $total_count, $page, $list_count)
	{
		if(!$list) return;
		$list_start_no = (int)$total_count - (((int)$page - 1) * (int)$list_count);
		$idx = 0;
		foreach($list as &$row)
		{
			$row->list_no = $list_start_no - $idx;
			$idx++;
		}
		unset($row);
	}

	/**
	 * @brief 관리자 목록 순번 (1부터 오름차순, 드래그 정렬 목록용)
	 */
	protected function assignAdminListNumbersAscending(&$list)
	{
		if(!$list) return;
		$no = 1;
		foreach($list as &$row)
		{
			$row->list_no = $no++;
		}
		unset($row);
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

		$this->assignAdminListNumbersAscending($channel_list);

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

		$this->assignAdminListNumbersAscending($region_list);

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

		$this->assignAdminListNumbersAscending($template_list);

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

		$this->assignAdminListNumbersAscending($extra_def_list);

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

		$list_count = (int)$args->list_count;
		$page = (int)$args->page;
		$total_count = (int)$output->total_count;

		$category_map = array();
		if($output->data)
		{
			$module_srls = array();
			foreach($output->data as $p)
			{
				if(!empty($p->module_srl)) $module_srls[(int)$p->module_srl] = true;
			}
			foreach(array_keys($module_srls) as $msrl)
			{
				$category_list = $oModel->getCategoryList($msrl);
				if(!$category_list) continue;
				foreach($category_list as $cat)
				{
					$category_map[$cat->category_srl] = $cat->title;
				}
			}

			$this->assignAdminListNumbersDescending($output->data, $total_count, $page, $list_count);
			foreach($output->data as &$product)
			{
				if(empty($product->regdate) && !empty($product->last_update))
				{
					$product->display_regdate = $product->last_update;
				}
				else
				{
					$product->display_regdate = $product->regdate;
				}
				$product->is_apply_closed = $oModel->isProductApplyClosed($product);
				$product->category_title = '';
				if($product->category_srl && isset($category_map[$product->category_srl]))
				{
					$product->category_title = $category_map[$product->category_srl];
				}
				elseif($product->category_srl)
				{
					$cat = $oModel->getCategory($product->category_srl);
					if($cat && isset($cat->title))
					{
						$product->category_title = $cat->title;
						$category_map[$product->category_srl] = $cat->title;
					}
				}
			}
			unset($product);
		}

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
			$this->assignAdminListNumbersDescending($output->data, (int)$output->total_count, (int)$args->page, (int)$args->list_count);
			foreach($output->data as &$app)
			{
				$app->product = $oModel->getProduct($app->product_srl);
				$app->member_info = $oMemberModel->getMemberInfoByMemberSrl($app->member_srl);
				$app->review = $oModel->getReviewByApplication($app->application_srl);
			}
			unset($app);
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
			$this->assignAdminListNumbersDescending($output->data, (int)$output->total_count, (int)$args->page, (int)$args->list_count);
			foreach($output->data as &$item)
			{
				$item->member_info = $oMemberModel->getMemberInfoByMemberSrl($item->business_member_srl);
			}
			unset($item);
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
			$this->assignAdminListNumbersDescending($output->data, (int)$output->total_count, (int)$args->page, (int)$args->list_count);
			foreach($output->data as &$report)
			{
				$report->reporter_info = $oMemberModel->getMemberInfoByMemberSrl($report->reporter_member_srl);

				$report->review_content = '';
				$report->reviewed_member_info = null;
				$report->application_srl = 0;
				$report->product_srl = 0;
				$report->product_title = '';

				if($report->review_type === 'member_review')
				{
					$mr = $oModel->getMemberReview($report->review_srl);
					if($mr)
					{
						$report->review_content = $mr->content;
						$report->reviewed_member_info = $oMemberModel->getMemberInfoByMemberSrl($mr->reviewer_member_srl);
						$report->application_srl = (int)$mr->application_srl;
						$report->product_srl = (int)$mr->product_srl;
					}
				}
				elseif($report->review_type === 'review_reply')
				{
					$reply = $oModel->getReviewReply($report->review_srl);
					if($reply)
					{
						$report->review_content = $reply->content;
						$report->reviewed_member_info = $oMemberModel->getMemberInfoByMemberSrl($reply->member_srl);
						$participant_review = $oModel->getReview($reply->review_srl);
						if($participant_review)
						{
							$report->application_srl = (int)$participant_review->application_srl;
							$report->product_srl = (int)$participant_review->product_srl;
						}
					}
				}
				else
				{
					$review = $oModel->getReview($report->review_srl);
					if($review)
					{
						$report->review_content = $review->content;
						$report->reviewed_member_info = $oMemberModel->getMemberInfoByMemberSrl($review->member_srl);
						$report->application_srl = (int)$review->application_srl;
						$report->product_srl = (int)$review->product_srl;
					}
				}

				if($report->product_srl)
				{
					$product = $oModel->getProduct($report->product_srl);
					if($product)
					{
						$report->product_title = $product->title;
					}
				}
			}
			unset($report);
		}

		$pmh_front_mid = $this->module_info ? $this->module_info->mid : '';
		Context::set('pmh_front_mid', $pmh_front_mid);

		Context::set('report_list', $output->data ?: array());
		Context::set('page_navigation', $output->page_navigation);
		Context::set('total_count', $output->total_count);
		Context::set('current_review_type', $review_type);

		$this->setTemplateFile('report_list');
	}

	/**
	 * @brief 리뷰/평가/대댓글 통합 조회 페이지
	 */
	function dispPoomahhiAdminReviewList()
	{
		$oModel = getModel('poomahhi');
		$oMemberModel = getModel('member');

		$args = new stdClass();
		$args->page = (int)(Context::get('page') ?: 1);
		$args->list_count = 15;

		$application_srl = (int)Context::get('application_srl');
		if($application_srl) $args->application_srl = $application_srl;

		$product_srl = (int)Context::get('product_srl');
		if($product_srl) $args->product_srl = $product_srl;

		$keyword = trim((string)Context::get('keyword'));
		if($keyword) $args->keyword = '%' . $keyword . '%';

		$output = executeQueryArray('poomahhi.getAdminReviewList', $args);

		$member_cache = array();
		$get_member = function($member_srl) use ($oMemberModel, &$member_cache) {
			if(!$member_srl) return null;
			$srl = (int)$member_srl;
			if(!isset($member_cache[$srl]))
			{
				$member_cache[$srl] = $oMemberModel->getMemberInfoByMemberSrl($srl);
			}
			return $member_cache[$srl];
		};

		if($output->data)
		{
			$this->assignAdminListNumbersDescending($output->data, (int)$output->total_count, (int)$args->page, (int)$args->list_count);
			foreach($output->data as &$row)
			{
				$m = $get_member($row->member_srl);
				$row->participant_nick = $m ? $m->nick_name : '탈퇴회원';
				$row->participant_profile = ($m && !empty($m->profile_image->src)) ? $m->profile_image->src : '';

				$mr = $oModel->getMemberReviewByApplication($row->application_srl);
				if($mr)
				{
					$reviewer = $get_member($mr->reviewer_member_srl);
					$mr->reviewer_nick = $reviewer ? $reviewer->nick_name : '탈퇴회원';
					$row->member_review = $mr;
				}
				else
				{
					$row->member_review = null;
				}

				$replies = $oModel->getReviewReplies($row->review_srl);
				if($replies)
				{
					foreach($replies as &$rp)
					{
						$rp_m = $get_member($rp->member_srl);
						$rp->nick_name = $rp_m ? $rp_m->nick_name : '탈퇴회원';
						$rp->profile_image = ($rp_m && !empty($rp_m->profile_image->src)) ? $rp_m->profile_image->src : '';
					}
					unset($rp);
				}
				$row->review_replies = $replies ?: array();
			}
			unset($row);
		}

		$pmh_front_mid = $this->module_info ? $this->module_info->mid : '';

		Context::set('review_list', $output->data ?: array());
		Context::set('page_navigation', $output->page_navigation);
		Context::set('total_count', $output->total_count ?: 0);
		Context::set('page', $args->page);
		Context::set('pmh_front_mid', $pmh_front_mid);
		Context::set('filter_application_srl', $application_srl);
		Context::set('filter_product_srl', $product_srl);
		Context::set('filter_keyword', $keyword);

		$this->setTemplateFile('admin_review_list');
	}
}
