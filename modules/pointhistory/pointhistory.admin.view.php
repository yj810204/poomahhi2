<?php
	/**
	 * @class  pointhistoryAdminView
     * @author CONORY (https://xe.conory.com)
	 * @brief The admin view class of the pointhistory module
	 */
	 
	class pointhistoryAdminView extends pointhistory
	{
		/**
		 * @brief Initialization
		 */
		function init()
		{
			Context::set('config', $this->config);
			
			$this->setTemplatePath($this->module_path . 'tpl');
			
			// 업그레이드 필요
			if($this->isUpgrade() && $this->act != 'dispPointhistoryAdminUpgrade')
			{
				$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispPointhistoryAdminUpgrade'));
			}
		}

		/**
		 * @brief 환경 설정
		 */
		function dispPointhistoryAdminConfig()
		{
			// 레이아웃
			$oLayoutModel = getModel('layout');
			Context::set('layout_list', $oLayoutModel->getLayoutList());
			Context::set('mlayout_list', $oLayoutModel->getLayoutList(0, 'M'));
			
			// 스킨
			$oModuleModel = getModel('module');
            Context::set('skin_list', $oModuleModel->getSkins($this->module_path));
			Context::set('mskin_list', $oModuleModel->getSkins($this->module_path, 'm.skins'));
			
			$this->setTemplateFile('config');
		}

		/**
		 * @brief 히스토리 목록
		 */
		function dispPointhistoryAdminList()
		{
            // 검색 옵션
			$search_option = array('nick_name', getModel('module')->getModuleConfig('member')->identifier, 'content');
            Context::set('search_option', $search_option);
			
			$args = new stdClass;
			
            if(($search_target = Context::get('search_target')) && in_array($search_target, $search_option))
			{
				$args->$search_target = Context::get('search_keyword');
            }
			
			$args->type = Context::get('type');
		    $args->page = Context::get('page');
            $output = executeQueryArray('pointhistory.getPointhistoryMemberList', $args);
			
            Context::set('total_count', $output->page_navigation->total_count);
            Context::set('total_page', $output->page_navigation->total_page);
            Context::set('page', $output->page);
            Context::set('history_list', $output->data);
            Context::set('page_navigation', $output->page_navigation);
			
			$this->setTemplateFile('list');
		}
		
		/**
		 * @brief 전체 현황
		 */
		function dispPointhistoryAdminStatus()
		{
		    if($this->config->point_status == 'N')
			{
				return new Object(-1, 'msg_invalid_request');
			}
			
			// 전체 포인트
			Context::set('total_point', executeQuery('pointhistory.getTodayStatus')->data->point);
			
			$args = new stdClass;
			$args->page = Context::get('page');
			$output = executeQueryArray('pointhistory.getPointhistoryStatusList');
			
            Context::set('total_count', $output->page_navigation->total_count);
            Context::set('total_page', $output->page_navigation->total_page);
            Context::set('page', $output->page);
            Context::set('status_list', $output->data);
            Context::set('page_navigation', $output->page_navigation);
			
			$this->setTemplateFile('status');
		}
		
		/**
		 * @brief 회원별 현황
		 */
		function dispPointhistoryAdminStatusMember()
		{
		    if($this->config->point_status == 'N')
			{
				return new Object(-1, 'msg_invalid_request');
			}
			
            // 검색 옵션
			$search_option = array('nick_name', getModel('module')->getModuleConfig('member')->identifier);
            Context::set('search_option', $search_option);
			
			$args = new stdClass;
			
            if(($search_target = Context::get('search_target')) && in_array($search_target, $search_option))
			{
				$args->$search_target = Context::get('search_keyword');
            }
			
		    $args->page = Context::get('page');
            $output = executeQueryArray('pointhistory.getPointhistoryStatusMemberList', $args);
			
            Context::set('total_count', $output->page_navigation->total_count);
            Context::set('total_page', $output->page_navigation->total_page);
            Context::set('page', $output->page);
            Context::set('status_list', $output->data);
            Context::set('page_navigation', $output->page_navigation);
			
			$this->setTemplateFile('status_member');
		}
		
		/**
		 * @brief 업그레이드
		 */
		function dispPointhistoryAdminUpgrade()
		{
			Context::set('max_count', $_SESSION['pointhistory_upgrade']['max_count']);
			
			$this->setTemplateFile('upgrade');
			
			// 업그레이드 완료시 이동
			if(!$this->isUpgrade())
			{
				$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispPointhistoryAdminConfig'));
			}
		}
	}