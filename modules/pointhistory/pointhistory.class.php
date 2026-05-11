<?php
	/**
	 * @class  pointhistory
     * @author CONORY (https://xe.conory.com)
	 * @brief The parent class of the pointhistory module
	 */
	
	class pointhistory extends ModuleObject
	{
		public $config = null;
		
		// ??? ?????
		private $triggers = array(
			array('point.setPoint', 'pointhistory', 'controller', 'triggerSetPoint', 'after'),
			array('moduleHandler.init', 'pointhistory', 'controller', 'triggerModuleHandler', 'after'),
			array('member.deleteMember', 'pointhistory', 'controller', 'triggerDeleteMember', 'after'),
		);
		
		// ???? ????? for ??????
		private $delete_triggers = array(
			array('moduleObject.proc', 'pointhistory', 'controller', 'triggerBeforeModuleObject', 'before'),
			array('moduleObject.proc', 'pointhistory', 'controller', 'triggerAfterModuleObject', 'after'),
			array('file.downloadFile', 'pointhistory', 'controller', 'triggerDownloadFile', 'after'),
		);
		
		function __construct()
		{
			$this->config = $this->getConfig();
			
			if(Context::get('module') == 'admin')
			{
				// ?????? ??? ??? ????
				if($this->config->delete_record_auto)
				{
					$args = new stdClass;
					$args->regdate_less = date('YmdHis', strtotime(sprintf('-%s day', $this->config->delete_record_auto)));
					executeQuery('pointhistory.deletePointhistoryLogLess', $args);
				}
				
				// ????? ??? ??? ??? ????
				if($this->config->delete_status_record_auto)
				{
					$args = new stdClass;
					$args->update_less = date('YmdHis', strtotime(sprintf('-%s day', $this->config->delete_status_record_auto)));
					executeQuery('pointhistory.deletePointhistoryStatusLess', $args);
					executeQuery('pointhistory.deletePointhistoryMemberStatusLess', $args);
				}
			}
		}
		
		/**
		 * @brief ??? ???
		 */
		function moduleInstall()
		{
            $oModuleModel = getModel('module');
            $oModuleController = getController('module');
			
			return new BaseObject();
		}

		/**
		 * @brief ??????? ??
		 */
		function checkUpdate()
		{
            $oDB = DB::getInstance();
            $oModuleModel = getModel('module');
			
			// ????? ???
			foreach($this->triggers as $trigger)
			{
				if(!$oModuleModel->getTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]))
				{
					return true;
				}
			}
			
			// ????? ???? for ??????
			foreach($this->delete_triggers as $trigger)
			{
				if($oModuleModel->getTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]))
				{
					return true;
				}
			}
			
			// ?????????? ?????? ??????? ????
			if(FileHandler::exists('modules/pointhistory/schemas/pointhistory.xml'))
			{
				return true;
			}
			
			// ???????? ???
			return $this->isUpgrade();
		}

		/**
		 * @brief ???????
		 */
		function moduleUpdate()
		{
            $oDB = DB::getInstance();
            $oModuleModel = getModel('module');
            $oModuleController = getController('module');
			
			// ????? ???
			foreach($this->triggers as $trigger)
			{
				if(!$oModuleModel->getTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]))
				{
					$oModuleController->insertTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
				}
			}
			
			// ????? ???? for ??????
			foreach($this->delete_triggers as $trigger)
			{
				if($oModuleModel->getTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]))
				{
					$oModuleController->deleteTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
				}
			}
			
			// ?????????? ?????? ??????? ????
			if(FileHandler::exists('modules/pointhistory/schemas/pointhistory.xml'))
			{
				return new BaseObject(-1, 'msg_pointhistory_overlay_directory');
			}
			
			// ???????? ???
			if($this->isUpgrade())
			{
				return new BaseObject(-1, 'msg_need_pointhistory_upgrade');
			}
			
			return new BaseObject(0, 'success_updated');
		}
	
		/**
		 * @brief ??? ????
		 */
		function moduleUninstall()
		{
			$oDB = DB::getInstance();
			$oModuleModel = getModel('module');
			$oModuleController = getController('module');
			
			// ????? ????
			foreach($this->triggers as $trigger)
			{
				if($oModuleModel->getTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]))
				{
					$oModuleController->deleteTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
				}
			}
			
			return new BaseObject();
		}
		
		/**
		 * @brief ???????? ?????
		 */
		function recompileCache()
		{
			// ?? ????? ?? ???
			if($this->config->point_status == 'Y')
			{
				$args = new stdClass;
				
				// ?????? ??????? ????
				if($this->config->status_except_admin == 'Y')
				{
					$args->is_admin = 'N';
				}
				
				$args->point = executeQuery('pointhistory.getPointAll', $args)->data->point_all;
				
				if(executeQuery('pointhistory.getTodayStatus')->data->count)
				{
					executeQuery('pointhistory.updateTodayStatus', $args);
				}
				else
				{
					executeQuery('pointhistory.insertTodayStatus', $args);
				}
			}
		}
		
 		/**
		 *@brief ????
		 **/
        function getConfig() 
		{
			$config = getModel('module')->getModuleConfig('pointhistory');
			if(!$config || !is_object($config))
			{
				$config = new stdClass();
			}

			$lang_list = Context::getLang('point_history_list');
			$lang_accumulate = Context::getLang('accumulate');
			$lang_use = Context::getLang('use');

			if(!isset($config->member_menu_name) || $config->member_menu_name === '' || $config->member_menu_name == $lang_list)
			{
				$config->member_menu_name = $lang_list;
			}
			if(!isset($config->increase_name) || $config->increase_name === '' || $config->increase_name == $lang_accumulate)
			{
				$config->increase_name = $lang_accumulate;
			}
			if(!isset($config->decrease_name) || $config->decrease_name === '' || $config->decrease_name == $lang_use)
			{
				$config->decrease_name = $lang_use;
			}

			$config->delete_record_leave = $config->delete_record_leave ?? 'Y';
			$config->delete_record_auto = $config->delete_record_auto ?? 0;
			$config->add_member_menu = $config->add_member_menu ?? 'Y';
			$config->point_unit_char = $config->point_unit_char ?? 'P';
			$config->skin = $config->skin ?? 'default';
			$config->mskin = $config->mskin ?? 'default';
			$config->point_status = $config->point_status ?? 'Y';
			$config->status_except_admin = $config->status_except_admin ?? 'Y';
			$config->delete_status_record_auto = $config->delete_status_record_auto ?? 0;

            return $config;
        }
		
 		/**
		 *@brief ???????? ??? ????
		 **/
        function isUpgrade()
		{
            $oDB = DB::getInstance();
			
			// ????? ???? ???
			if($oDB->isTableExists('pointhistory'))
			{
				return true;
			}
			
			return false;
		}
	}