<?php
	/**
	 * @class  pointhistoryView
	 * @author CONORY (https://xe.conory.com)
	 * @brief The view class of the pointhistory module
	 */
	class pointhistoryView extends pointhistory
	{
		/**
		 * @brief Initialization
		 */
        function init() 
		{
			Context::set('config', $this->config);
			
			$this->setTemplatePath(sprintf('%sskins/%s/', $this->module_path, $this->config->skin));
			
			// 레이아웃
			if($this->config->layout_srl)
			{
				if($layout_info = getModel('layout')->getLayout($this->config->layout_srl))
				{
					$this->module_info->layout_srl = $this->config->layout_srl;
					$this->setLayoutPath($layout_info->path);
				}
			}
        }

		/**
		 * @brief 회원 포인트 히스토리 목록
		 */
		function dispPointhistoryList()
		{
		    if(!Context::get('is_logged'))
			{
				return new BaseObject(-1, 'msg_not_logged');
			}
			
		    if($this->isUpgrade())
			{
				return new BaseObject(-1, 'msg_invalid_request');
			}
			
			$args = new stdClass;
			$args->type = Context::get('type');
		    $args->page = Context::get('page');
			$args->member_srl = Context::get('logged_info')->member_srl;
            $output = executeQuery('pointhistory.getPointhistoryLogList', $args);

            Context::set('total_count', $output->page_navigation->total_count);
            Context::set('total_page', $output->page_navigation->total_page);
            Context::set('page', $output->page);
            Context::set('history_list', $output->data);
            Context::set('page_navigation', $output->page_navigation);
			
            $this->setTemplateFile('list');
		}
	}