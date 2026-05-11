<?php
	/**
	 * @class  pointhistoryAdminController
     * @author CONORY (https://xe.conory.com)
	 * @brief The admin controller class of the pointhistory module
	 */
	
	class pointhistoryAdminController extends pointhistory 
	{
		/**
		 * @brief Initialization
		 */
		function init()
		{
		}

        /**
         * @brief ȯ�漳��
         **/
        function procPointhistoryAdminConfig() 
		{
            $args = Context::getRequestVars();
			
            getController('module')->insertModuleConfig('pointhistory', $args);	
			
			// ������ ���� ���� ���� ����Ǿ��� ��� �� ����Ʈ �� ���
			if($this->config->status_except_admin != $args->status_except_admin)
			{
				$this->config = $this->getConfig();
				$this->recompileCache();
			}
			
            $this->setMessage('success_updated');
			$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispPointhistoryAdminConfig'));
        }
		
        /**
         * @brief �����丮 ����
         **/
        function procPointhistoryAdminDeleteHistory()
		{
			$args = new stdClass;
			if(Context::get('date_srl'))
			{
				$args->history_id = Context::get('date_srl');
			}
			
            $output = executeQuery('pointhistory.deletePointhistoryLog', $args);
            if(!$output->toBool())
			{
				return $output;
			}
			
            $this->setMessage('success_deleted');
			$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispPointhistoryAdminList', 'page', Context::get('page')));
        }
		
        /**
         * @brief ��ü ��Ȳ ����
         **/
        function procPointhistoryAdminDeleteStatus()
		{
            $output = executeQuery('pointhistory.deletePointhistoryStatus');
            if(!$output->toBool())
			{
				return $output;
			}
			
			// �� ����
			getController('pointhistory')->insertTodayStatus();
			
            $this->setMessage('success_deleted');
			$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispPointhistoryAdminStatus', 'page', Context::get('page')));
        }
		
        /**
         * @brief ȸ���� ��Ȳ ����
         **/
        function procPointhistoryAdminDeleteStatusMember()
		{
            $output = executeQuery('pointhistory.deletePointhistoryMemberStatus');
            if(!$output->toBool())
			{
				return $output;
			}
			
            $this->setMessage('success_deleted');
			$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispPointhistoryAdminStatusMember', 'page', Context::get('page')));
        }
		
		/**
		 * @brief ���׷��̵�
		 */
		function procPointhistoryAdminUpgrade()
		{
			@set_time_limit(0);
			// @ini_set('memory_limit', '512M');
			
			$oDB = DB::getInstance();
			
			// ���׷��̵尡 �ʿ� ���ٸ�
			if(!$this->isUpgrade())
			{
				return new BaseObject(-1, 'msg_invalid_request');
			}
			
			// �� ���̺��� ���� �����Ǿ����� �ʴٸ�
			if(!$oDB->isTableExists('pointhistory_log'))
			{
				return new BaseObject(-1, 'msg_pointhistory_no_new_table');
			}
			
			// ���� ����ϱ�
			if(Context::get('type') == 'new')
			{
				// ���̺� ����
				$oDB->dropTable('pointhistory');
				
				return $this->setMessage('success_upgrade_use_new');
			}
			
			############### ���׷��̵� ����
			
			// �ʱ�ȭ
			if(!is_array($_SESSION['pointhistory_upgrade']))
			{
				// �� ���̺��� �����Ͱ� ������ ��� ����
				executeQuery('pointhistory.deletePointhistoryLog');
				
				// ���׷��̵� ����
				$_SESSION['pointhistory_upgrade'] = array();
				$_SESSION['pointhistory_upgrade']['page'] = 1;
				$_SESSION['pointhistory_upgrade']['current'] = 0;
				$_SESSION['pointhistory_upgrade']['max_count'] = Context::get('max_count');
			}
			
			// �ѹ��� �ű� �ִ� ó���� (������ ������ �����ʵ���)
			$max_count = $_SESSION['pointhistory_upgrade']['max_count'] ?: 100;
			
			// ������ ������ ��������
			if(!is_array($_SESSION['pointhistory_upgrade']['output']))
			{
				$args = new stdClass;
				$args->list_count = $max_count;
				$args->page = $_SESSION['pointhistory_upgrade']['page'];
				
				$output = executeQueryArray('pointhistory.getPointhistoryOld', $args);
				if(!$output->toBool())
				{
					return new BaseObject(-1, 'failure_upgrade');
				}
				
				$_SESSION['pointhistory_upgrade']['output'] = $output;
				
				unset($output);
			}
			
			$current_count = 1;
			
			$old_output = $_SESSION['pointhistory_upgrade']['output'];
			
			// ���ο� ���̺��� ����
			foreach($old_output->data as $val)
			{
				// �߰��� ������ ��� �̾ ���� ����
				if($_SESSION['pointhistory_upgrade']['current'] >= $current_count)
				{
					continue;
				}
				
				$args = new stdClass;
				$args->member_srl = $val->member_srl;
				$args->point = ($val->htype == 'M') ? $val->point * -1 : $val->point;
				$args->type = ($val->htype == 'M') ? 1 : 2;
				$args->message_type = 'old';
				$args->message = $val->content;
				$args->act = $val->act;
				$args->module_srl = $val->module_srl;
				$args->ipaddress = $val->ipaddress;
				$args->regdate = $val->regdate;
				$output = executeQuery('pointhistory.insertPointhistoryLog', $args);
				
				if(!$output->toBool())
				{
					return new BaseObject(-1, 'failure_upgrade');
				}
				
				// ������ �κ� ����
				$_SESSION['pointhistory_upgrade']['current'] = $current_count;
				
				$current_count++;
			}
			
			// ���� ���� �����Ͱ� �ִٸ� (�ڹٽ�ũ��Ʈ���� ���û��)
			if($old_output->page_navigation->total_page != $_SESSION['pointhistory_upgrade']['page'])
			{
				$total_count = $old_output->page_navigation->total_count;
				$complete = $max_count * $_SESSION['pointhistory_upgrade']['page'];
				
				// ����� ����
				$this->add('total', number_format($total_count));
				$this->add('current', number_format($complete));
				$this->add('percent', round($complete / $total_count * 100));
				
				// ó������ ���� ���û �ð�
				if($max_count <= 1000)
				{
					$this->add('timeout', 1000);
				}
				else if($max_count <= 5000)
				{
					$this->add('timeout', 2000);
				}
				else
				{
					$this->add('timeout', 3000);
				}
				
				// ������ �ѱ��
				$_SESSION['pointhistory_upgrade']['page']++;
				
				// ���� ������ ���� �ʱ�ȭ
				$_SESSION['pointhistory_upgrade']['current'] = 0;
				unset($_SESSION['pointhistory_upgrade']['output']);
				
				return;
			}
			
			############### ���׷��̵� ��
			
			// ���̺� ����
			$oDB->dropTable('pointhistory');
			
			// ���� ����
			unset($_SESSION['pointhistory_upgrade']);
			
			$this->add('percent', 100);
			$this->setMessage('success_upgrade');
		}
	}