<?php
	/**
	 * @class  pointhistoryController
     * @author CONORY (https://xe.conory.com)
	 * @brief Controller class of pointhistory modules
	 */
	class pointhistoryController extends pointhistory
	{
		/**
		 * @brief Initialization
		 */
		function init()
		{
		}
		
 		/**
		 *@brief Module Handler Ʈ����
		 **/
        function triggerModuleHandler(&$obj)
		{
            if($this->isUpgrade())
			{
				return new BaseObject();
			}
			
			// ����Ʈ ��Ȳ ������Ʈ
			$this->updatePointStatus();
			
			// ȸ�� �޴��� ����Ʈ ���� �߰�
			if(Context::get('is_logged') && $this->config->add_member_menu == 'Y')
			{
				getController('member')->addMemberMenu('dispPointhistoryList', $this->config->member_menu_name);
			}
			
            return new BaseObject();
        }
		
 		/**
		 *@brief ����Ʈ ���� Ʈ��Ŀ
		 **/
        function triggerSetPoint(&$obj)
		{
			// ������ ������ return
			if($obj->current_point == $obj->set_point || $this->isUpgrade())
			{
				return new BaseObject();
			}
			
			$args = new stdClass;
			$args->member_srl = $obj->member_srl;
			$args->point = $obj->set_point - $obj->current_point;
			$args->type = ($args->point < 0) ? 1 : 2;
			$args->act = Context::get('act');
			$args->module_srl = Context::get('current_module_info')->module_srl;
			
			// ����Ʈ �޼��� ��������
			getModel('pointhistory')->getPointMessage($args);
			
			// ����Ʈ ���
			executeQuery('pointhistory.insertPointhistoryLog', $args);
			
			// ȸ�� ����Ʈ ��Ȳ ������Ʈ
			$this->updatePointStatus($obj->member_srl, $args->point);
			
			return new BaseObject();
        }
		
 		/**
		 *@brief ȸ������ Ʈ����
		 **/
        function triggerDeleteMember($obj)
		{
			$this->recompileCache();
			
			if(!$obj->member_srl || $this->config->delete_record_leave == 'N' || $this->isUpgrade())
			{
				return new BaseObject();
			}
			
			$args = new stdClass;
			$args->member_srl = $obj->member_srl;
			
			// ȸ�� �����丮 ����
            executeQuery('pointhistory.deletePointhistoryLog', $args);
			
			// ȸ�� ��Ȳ ����
            executeQuery('pointhistory.deletePointhistoryMemberStatus', $args);
			
			return new BaseObject();
		}
		
 		/**
		 *@brief ����Ʈ ��Ȳ ������Ʈ
		 **/
        function updatePointStatus($member_srl = 0, $point = 0)
		{
			// ����Ʈ ��Ȳ ������
			if($this->config->point_status == 'N')
			{
				return;
			}
			
			// ���� ��¥�� ���ٸ� �߰�
			if(!getModel('pointhistory')->isInsertedTodayStatus($member_srl))
			{
				$this->insertTodayStatus($member_srl);
			}
			
			if(!$member_srl || !$point)
			{
				return;
			}
			
			// �����ڴ� ����
			if($this->config->status_except_admin == 'Y' && getModel('member')->getMemberInfoByMemberSrl($member_srl)->is_admin == 'Y')
			{
				return;
			}
			
			$args = new stdClass;
			$args->member_srl = $member_srl;
			$args->day = getModel('pointhistory')->getToday();
			
			// ȸ�� ��Ȳ ������Ʈ
			$args->point = executeQuery('pointhistory.getTodayMemberStatus', $args)->data->point + $point;
			executeQuery('pointhistory.updateTodayMemberStatus', $args);
			
			// ���� ��Ȳ ������Ʈ
			$args->point = executeQuery('pointhistory.getTodayStatus', $args)->data->point + $point;
			executeQuery('pointhistory.updateTodayStatus', $args);
			
			// �� ��Ȳ ������Ʈ
			$args = new stdClass;
			$args->point = executeQuery('pointhistory.getTodayStatus')->data->point + $point;
			executeQuery('pointhistory.updateTodayStatus', $args);
        }
		
 		/**
		 *@brief ���� ��¥ �߰�
		 **/
		function insertTodayStatus($member_srl = 0)
		{
			// �ߺ� ���� ������ ���� ��
			if(!$member_srl)
			{
				$lockfile = 'files/cache/pointhistory/insertTodayStatus.lock';
				
				if(file_exists($lockfile))
				{
					return;
				}
				
				FileHandler::writeFile($lockfile, '', 'w');
			}
			// �����ڴ� ����
			else if($this->config->status_except_admin == 'Y' && getModel('member')->getMemberInfoByMemberSrl($member_srl)->is_admin == 'Y')
			{
				return;
			}
			
			$args = new stdClass;
			$args->day = getModel('pointhistory')->getToday();
			
			if($member_srl)
			{
				$args->member_srl = $member_srl;
				$query_id = 'pointhistory.insertTodayMemberStatus';
			}
			else
			{
				$query_id = 'pointhistory.insertTodayStatus';
				
				// day=0 �� ����Ʈ
				if(!executeQuery('pointhistory.getTodayStatus')->data->count)
				{
					$args2 = new stdClass;
					
					// ������ ����Ʈ�� ����
					if($this->config->status_except_admin == 'Y')
					{
						$args2->is_admin = 'N';
					}
					
					$args2->point = executeQuery('pointhistory.getPointAll', $args2)->data->point_all;
					executeQuery($query_id, $args2);
				}
			}
			
			executeQuery($query_id, $args);
			
			// �� ���� ����
			if(!$member_srl)
			{
				FileHandler::removeFile($lockfile);
			}
		}
	}