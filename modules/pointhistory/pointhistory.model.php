<?php
	/**
	 * @class  pointhistoryModel
	 * @author CONORY (https://xe.conory.com)
	 * @brief The model class fo the pointhistory module
	 */
	class pointhistoryModel extends pointhistory
	{
		/**
		 * @brief Initialization
		 */
		function init()
		{
		}
		
 		/**
		 *@brief 포인트 메세지
		 **/
        function getPointMessage(&$obj)
		{
			// 개발자 지정 메세지
			if($obj->message = Context::get('__point_message__'))
			{
				$obj->message_type = Context::get('__point_message_type__') ?: 'plugin';
				
				Context::set('__point_message__', null);
				Context::set('__point_message_type__', null);
				
				return array('type' => $obj->message_type, 'message' => $obj->message);
			}
			
			// 구버전 지정 메세지 코드 호환
			if($__PHC__ = Context::get('__PHC' . $obj->member_srl . '__'))
			{
				$obj->message_type = 'plugin';
				$obj->message = $__PHC__[count($__PHC__)-1][0];
				
				return array('type' => $obj->message_type, 'message' => $obj->message);
			}
			
			// 첨부 파일 다운로드시 연결된 실제 모듈로 변경
			if($obj->act == 'procFileDownload')
			{
				$args = new stdClass;
				$args->file_srl = Context::get('file_srl');
				$file = executeQuery('file.getFile', $args)->data;
				
				// 다운로드 URL 구하기
				$file->download_url = getModel('file')->getDownloadUrl($file->file_srl, $file->sid, $file->module_srl);
				
				// 실제 모듈로 변경
				$obj->module_srl = $file->module_srl;
				$obj->reference_srl = $file->upload_target_srl;
			}
			
			// 모듈 브라우저 제목
			if($obj->module_srl)
			{
				$module_title = getModel('module')->getModuleInfoByModuleSrl($obj->module_srl)->browser_title;
			}
			
			// 아니면 모듈 이름 찾기
			if(!$module_title && $module_name = Context::get('current_module_info')->module)
			{
				$module_title = getModel('module')->getModuleInfoXml($module_name)->title;
			}
			
			// 그래도 모듈 제목이 없으면 '알 수 없음'
			if(!$module_title)
			{
				$module_unknown = true;
				$module_title = Context::getLang('point_message_unknown');
			}
			
			// 게시물 정보
			if($document_srl = in_array($obj->act, array('procDocumentVoteUp', 'procDocumentVoteDown')) ? Context::get('target_srl') : Context::get('document_srl'))
			{
				$oDocument = getModel('document')->getDocument($document_srl);
				if($oDocument->isExists())
				{
					$obj->reference_srl = $document_srl;
					
					// 게시물 열람
					if(!Context::get('act'))
					{
						$obj->message_type = 'read_document';
						$obj->message = sprintf(Context::getLang('point_message_read_document'), $module_title, getUrl('', 'document_srl', $document_srl), cut_str($oDocument->get('title'), 30));
						
						return array('type' => $obj->message_type, 'message' => $obj->message);
					}
				}
			}
			
			// ACT별 메세지
			switch($obj->act)
			{
				// 로그인
				case 'procMemberLogin' :
					$obj->message_type = 'member_login';
					$obj->message = Context::getLang('point_message_member_login');
					$obj->module_srl = $obj->reference_srl = 0;
					break;
				
				// 가입
				case 'procMemberInsert' :
					$obj->message_type = 'member_join';
					$obj->message = Context::getLang('point_message_member_join');
					$obj->module_srl = $obj->reference_srl = 0;
					break;
					
				// 게시물 작성
				case 'procBoardInsertDocument' :
					$obj->message_type = 'insert_document';
					$obj->message = sprintf(Context::getLang('point_message_insert_document'), $module_title, cut_str(Context::get('title'), 30));
					break;
					
				// 게시물 삭제
				case 'procBoardDeleteDocument' :
					$obj->message_type = 'delete_document';
					$obj->message = sprintf(Context::getLang('point_message_delete_document'), $module_title);
					break;
					
				// 댓글 작성
				case 'procBoardInsertComment' :
					$obj->message_type = 'insert_comment';
					$obj->message = sprintf(Context::getLang('point_message_insert_comment'), $module_title);
					break;
					
				// 댓글 삭제
				case 'procBoardDeleteComment' :
					$obj->message_type = 'delete_comment';
					$obj->message = sprintf(Context::getLang('point_message_delete_comment'), $module_title);
					break;
					
				// 첨부 파일 다운로드
				case 'procFileDownload' :
					$obj->message_type = 'download_file';
					$obj->message = sprintf(Context::getLang('point_message_download_file'), $module_title, $file->download_url, stripslashes($file->source_filename));
					break;
				
				// 첨부 파일 삭제
				case 'procFileDelete' :
					$obj->message_type = 'delete_file';
					$obj->message = Context::getLang('point_message_delete_file');
					break;
				
				// 관리자 포인트 조정
				case 'procPointAdminUpdatePoint' :
					$obj->message_type = 'update_admin';
					$obj->message = Context::getLang('point_message_update_admin');
					$obj->module_srl = $obj->reference_srl = 0;
					break;
				
				// 추천 받음
				case 'procDocumentVoteUp' :
					$obj->message_type = 'vote_up';
					$obj->message = sprintf(Context::getLang('point_message_document_vote_up'), $module_title, getUrl('', 'document_srl', $document_srl), cut_str($oDocument->get('title'), 30));
					break;
					
				// 비추천 받음
				case 'procDocumentVoteDown' :
					$obj->message_type = 'vote_down';
					$obj->message = sprintf(Context::getLang('point_message_document_vote_down'), $module_title, getUrl('', 'document_srl', $document_srl), cut_str($oDocument->get('title'), 30));
					break;
					
				// 게시물 관리에 의한 포인트 변동
				case 'procDocumentManageCheckedDocument' :
					$obj->message_type = 'document_manage';
					$obj->message = Context::getLang('point_message_document_manage');
					$obj->module_srl = $obj->reference_srl = 0;
					break;
					
				// 소셜 XE
				case 'procSocialxeInputAddInfo' :
				case 'procSocialxeConfirmMail' :
				case 'procSocialxeCallback' :
					// 가입
					if(Context::get('accept_agreement') == 'Y')
					{
						$obj->message_type = 'sns_join';
						$obj->message = Context::getLang('point_message_member_join_sns');
					}
					// 로그인
					else
					{
						$obj->message_type = 'sns_login';
						$obj->message = Context::getLang('point_message_member_login');
					}
					
					$obj->module_srl = $obj->reference_srl = 0;
					break;
			}
			
			// 지정된 메세지가 없다면
			if(!$obj->message)
			{
				// 자동 로그인
				if($module_unknown && $_COOKIE['xeak'])
				{
					$member_info = getModel('member')->getMemberInfoByMemberSrl($obj->member_srl);
					if($member_info->last_login > date('YmdHis', strtotime('-1 minute')))
					{
						$obj->message_type = 'auto_login';
						$obj->message = Context::getLang('point_message_member_login_auto');
						
						return array('type' => $obj->message_type, 'message' => $obj->message);
					}
				}
				
				// 기본 메세지로 출력
				if($obj->point < 0)
				{
					$obj->message_type = 'minus';
					$obj->message = sprintf(Context::getLang('point_message_content_minus'), $module_title, $this->config->decrease_name);
				}
				else
				{
					$obj->message_type = 'add';
					$obj->message = sprintf(Context::getLang('point_message_content_add'), $module_title, $this->config->increase_name);
				}
			}
			
			return array('type' => $obj->message_type, 'message' => $obj->message);
        }
		
 		/**
		 *@brief 오늘 날짜 반환 (Ymd)
		 **/
		function getToday($interval = '')
		{
			if($interval)
			{
				$today = date('Ymd', strtotime($interval . ' day') + zgap());
			}
			else
			{
				$today = date('Ymd', time() + zgap());
			}
			
			return $today;
		}
		
 		/**
		 *@brief 오늘 날짜 현황 존재 여부
		 **/
		function isInsertedTodayStatus($member_srl = 0)
		{
			$is_inserted = false;
			
			$args = new stdClass;
			$args->day = $this->getToday();
			
			if($member_srl)
			{
				$args->member_srl = $member_srl;
				$query_id = 'getTodayMemberStatus';
				$type = ':' . $member_srl . ':';
			}
			else
			{
				$query_id = 'getTodayStatus';
				$type = ':total:';
			}
			
			$oCacheHandler = CacheHandler::getInstance('object', null, true);
			if($oCacheHandler->isSupport())
			{
				$is_inserted = $oCacheHandler->get('pointhistory:' . $query_id . $type . $args->day);
			}

			if($is_inserted === false)
			{
				$output = executeQuery('pointhistory.' . $query_id, $args);
				
				if(($is_inserted = !!$output->data->count) && $oCacheHandler->isSupport())
				{
					// 캐시 저장
					$oCacheHandler->put('pointhistory:' . $query_id . $type . $args->day, true);
					
					// 지난 캐시 삭제
					$oCacheHandler->delete('pointhistory:' . $query_id . $type . $this->getToday('-1'));
				}
			}

			return $is_inserted;
		}
	}