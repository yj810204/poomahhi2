<?php
	/**
	 * @class  pointhistoryMobile
	 * @author CONORY (https://xe.conory.com)
	 * @brief The mobile class of the pointhistory module
	 */
	
	require_once(_XE_PATH_ . 'modules/pointhistory/pointhistory.view.php');	
	
	class pointhistoryMobile extends pointhistoryView
	{

		function init()
		{
			Context::set('config', $this->config);
			
			$this->setTemplatePath(sprintf('%sm.skins/%s/', $this->module_path, $this->config->mskin));
			
			// 레이아웃
			if($this->config->mlayout_srl)
			{
				if($layout_info = getModel('layout')->getLayout($this->config->mlayout_srl))
				{
					$this->module_info->mlayout_srl = $this->config->mlayout_srl;
					$this->setLayoutPath($layout_info->path);
				}
			}
		}
	}