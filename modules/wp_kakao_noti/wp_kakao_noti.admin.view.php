<?php
/**
 * @class wp_kakao_notiAdminView
 * @brief 관리자 뷰
 */
class wp_kakao_notiAdminView extends wp_kakao_noti
{
	function init()
	{
		$this->setTemplatePath($this->module_path . 'tpl/');
		Context::set('wkn_mid', wp_kakao_noti::wknCurrentMid());
		$msrl = Context::get('module_srl');
		Context::set('wkn_module_srl', ($msrl !== null && $msrl !== '') ? (string)(int)$msrl : '');
	}

	function dispWp_kakao_notiAdminConfig()
	{
		$oModel = getModel('wp_kakao_noti');
		Context::set('config', $oModel->getModuleConfig());
		$msrl = Context::get('module_srl');
		Context::set('wkn_solapi_channel_count', 0);

		// 저장된 API 키로 연결 테스트 (GET ?solapi_test=1, 링크로 호출)
		if(trim((string)Context::get('solapi_test')) === '1')
		{
			$res = $oModel->testSolapiConnection(array());
			Context::set('wkn_solapi_test_done', true);
			Context::set('wkn_solapi_test_http_status', (int)($res['status'] ?? 0));
			if(!empty($res['error']) && $res['error'] === 'no_auth')
			{
				Context::set('wkn_solapi_test_ok', false);
				Context::set('wkn_solapi_test_msg', 'API Key 또는 Secret이 저장되어 있지 않습니다.');
			}
			elseif(!empty($res['error']))
			{
				Context::set('wkn_solapi_test_ok', false);
				Context::set('wkn_solapi_test_msg', (string)$res['error']);
			}
			elseif(empty($res['ok']))
			{
				Context::set('wkn_solapi_test_ok', false);
				$msg = $oModel->formatSolapiErrorForDisplay(is_array($res['body']) ? $res['body'] : null, (string)$res['raw'], (int)$res['status']);
				Context::set('wkn_solapi_test_msg', $msg !== '' ? $msg : 'SOLAPI 요청이 실패했습니다.');
			}
			else
			{
				Context::set('wkn_solapi_test_ok', true);
				Context::set('wkn_solapi_test_msg', 'SOLAPI 인증에 성공했습니다.');
			}
		}
		else
		{
			Context::set('wkn_solapi_test_done', false);
		}

		$test_url_params = array('', 'module', 'admin', 'mid', wp_kakao_noti::wknCurrentMid(), 'act', 'dispWp_kakao_notiAdminConfig', 'solapi_test', '1');
		if($msrl !== null && $msrl !== '' && (int)$msrl > 0)
		{
			$test_url_params[] = 'module_srl';
			$test_url_params[] = (int)$msrl;
		}
		Context::set('wkn_solapi_test_get_url', call_user_func_array('getNotEncodedUrl', $test_url_params));

		// GET ?solapi_channels=1 — SOLAPI 연동 카카오톡 채널 목록 (channelId 확인용, GET /kakao/v2/channels)
		Context::set('wkn_solapi_channels_done', false);
		Context::set('wkn_solapi_channels_ok', false);
		Context::set('wkn_solapi_channels_msg', '');
		Context::set('wkn_solapi_channel_list', array());
		Context::set('wkn_solapi_channels_http_status', 0);
		Context::set('wkn_solapi_channels_next_url', '');
		if(trim((string)Context::get('solapi_channels')) === '1')
		{
			$chStartKey = trim((string)Context::get('solapi_channels_startKey'));
			$chRes = $oModel->fetchSolapiKakaoChannels(array(), 100, $chStartKey);
			Context::set('wkn_solapi_channels_done', true);
			Context::set('wkn_solapi_channels_http_status', (int)($chRes['status'] ?? 0));
			if($chRes['error'] === 'no_auth')
			{
				Context::set('wkn_solapi_channels_ok', false);
				Context::set('wkn_solapi_channels_msg', 'API Key 또는 Secret이 저장되어 있지 않습니다.');
			}
			elseif($chRes['error'] !== '')
			{
				Context::set('wkn_solapi_channels_ok', false);
				Context::set('wkn_solapi_channels_msg', $chRes['error']);
			}
			elseif(empty($chRes['ok']))
			{
				Context::set('wkn_solapi_channels_ok', false);
				$msg = $oModel->formatSolapiErrorForDisplay(is_array($chRes['body']) ? $chRes['body'] : null, (string)$chRes['raw'], (int)$chRes['status']);
				Context::set('wkn_solapi_channels_msg', $msg !== '' ? $msg : '채널 목록 요청이 실패했습니다.');
			}
			else
			{
				Context::set('wkn_solapi_channels_ok', true);
				Context::set('wkn_solapi_channel_list', $chRes['channels']);
				$cnt = is_array($chRes['channels']) ? count($chRes['channels']) : 0;
				Context::set('wkn_solapi_channel_count', $cnt);
				if($cnt === 0)
				{
					Context::set('wkn_solapi_channels_msg', '연동된 카카오톡 채널이 없습니다. 솔라피에서 채널 연동 후 다시 조회하세요.');
				}
				else
				{
					Context::set('wkn_solapi_channels_msg', '채널 ' . $cnt . '건을 불러왔습니다. 아래 channelId를 복사하거나 &quot;입력란에 넣기&quot;를 사용하세요.');
				}
				$nextKey = trim((string)($chRes['nextKey'] ?? ''));
				if($nextKey !== '')
				{
					$next_params = array('', 'module', 'admin', 'mid', wp_kakao_noti::wknCurrentMid(), 'act', 'dispWp_kakao_notiAdminConfig', 'solapi_channels', '1', 'solapi_channels_startKey', $nextKey);
					if($msrl !== null && $msrl !== '' && (int)$msrl > 0)
					{
						$next_params[] = 'module_srl';
						$next_params[] = (int)$msrl;
					}
					Context::set('wkn_solapi_channels_next_url', call_user_func_array('getNotEncodedUrl', $next_params));
				}
			}
		}

		$channels_url_params = array('', 'module', 'admin', 'mid', wp_kakao_noti::wknCurrentMid(), 'act', 'dispWp_kakao_notiAdminConfig', 'solapi_channels', '1');
		if($msrl !== null && $msrl !== '' && (int)$msrl > 0)
		{
			$channels_url_params[] = 'module_srl';
			$channels_url_params[] = (int)$msrl;
		}
		Context::set('wkn_solapi_channels_get_url', call_user_func_array('getNotEncodedUrl', $channels_url_params));

		$this->setTemplateFile('config');
	}

	function dispWp_kakao_notiAdminSendLog()
	{
		$oModel = getModel('wp_kakao_noti');
		$page = (int)Context::get('page');
		if($page < 1)
		{
			$page = 1;
		}
		$filter_status = trim((string)Context::get('filter_status'));

		$args = new stdClass();
		$args->page = $page;
		$args->list_count = 30;
		$args->page_count = 10;

		$output = $oModel->getSendLogList($args, ($filter_status !== '') ? $filter_status : null);
		$list = array();
		if($output->toBool() && $output->data)
		{
			$list = $output->data;
			if(!is_array($list))
			{
				$list = array($list);
			}
		}

		Context::set('log_list', $list);
		Context::set('total_count', isset($output->total_count) ? (int)$output->total_count : 0);
		Context::set('total_page', isset($output->total_page) ? (int)$output->total_page : 1);
		Context::set('page', $page);
		Context::set('page_navigation', $output->page_navigation ?? null);
		Context::set('filter_status', $filter_status);

		$this->setTemplateFile('send_log');
	}

	function dispWp_kakao_notiAdminTemplateList()
	{
		$oModel = getModel('wp_kakao_noti');
		$config = $oModel->getModuleConfig();
		$channelId = trim((string)$config->solapi_channel_id);
		$list = array();
		if($channelId !== '')
		{
			$path = '/kakao/v2/templates/?channelId=' . rawurlencode($channelId) . '&limit=100';
			$res = $oModel->solapiRequest('GET', $path);
			$raw = array();
			if(!empty($res['ok']) && is_array($res['body']) && isset($res['body']['templateList']))
			{
				$raw = $res['body']['templateList'];
			}
			elseif(!empty($res['ok']) && is_array($res['body']) && isset($res['body'][0]))
			{
				$raw = $res['body'];
			}
			if(is_array($raw))
			{
				foreach($raw as $row)
				{
					if(is_array($row))
					{
						$obj = (object)$row;
					}
					elseif(is_object($row))
					{
						$obj = $row;
					}
					else
					{
						continue;
					}
					$obj->wkn_status_label_ko = $oModel->getKakaoAlimtalkTemplateStatusLabelKo(isset($obj->status) ? (string)$obj->status : '');
					$obj->wkn_tpl_inspecting = $oModel->isKakaoAlimtalkTemplateInspecting(isset($obj->status) ? (string)$obj->status : '');
					$list[] = $obj;
				}
			}
		}
		Context::set('template_list', is_array($list) ? $list : array());
		Context::set('config', $config);
		$this->setTemplateFile('template_list');
	}

	function dispWp_kakao_notiAdminTemplateWrite()
	{
		$oModel = getModel('wp_kakao_noti');
		$config = $oModel->getModuleConfig();
		$categories = array();
		$catRes = $oModel->solapiRequest('GET', '/kakao/v2/templates/categories');
		if(!empty($catRes['ok']) && is_array($catRes['body']))
		{
			$categories = $catRes['body'];
		}

		$template_id = trim((string)Context::get('template_id'));
		$tpl = null;
		if($template_id !== '')
		{
			$res = $oModel->solapiRequest('GET', '/kakao/v2/templates/' . rawurlencode($template_id));
			if(!empty($res['ok']) && is_array($res['body']))
			{
				$tpl = (object)$res['body'];
			}
		}

		$buttons_json_disp = '';
		if($tpl && isset($tpl->buttons) && is_array($tpl->buttons))
		{
			$buttons_json_disp = json_encode($tpl->buttons, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
		}

		if($tpl)
		{
			Context::set('tpl_name_forform', htmlspecialchars(isset($tpl->name) ? (string)$tpl->name : '', ENT_QUOTES, 'UTF-8'));
			Context::set('tpl_category_forform', htmlspecialchars(isset($tpl->categoryCode) ? (string)$tpl->categoryCode : '', ENT_QUOTES, 'UTF-8'));
			$content_raw = isset($tpl->content) ? (string)$tpl->content : '';
			$content_for_textarea = $oModel->normalizeAlimtalkTemplateContentForDisplay($content_raw);
			Context::set('tpl_content_forform', htmlspecialchars($content_for_textarea, ENT_QUOTES, 'UTF-8'));
		}
		else
		{
			Context::set('tpl_name_forform', '');
			Context::set('tpl_category_forform', '');
			Context::set('tpl_content_forform', '');
		}
		Context::set('buttons_json_disp', htmlspecialchars($buttons_json_disp, ENT_QUOTES, 'UTF-8'));

		$write_blocked = false;
		if($tpl && $oModel->isKakaoAlimtalkTemplateInspecting(isset($tpl->status) ? (string)$tpl->status : ''))
		{
			$write_blocked = true;
		}
		Context::set('wkn_tpl_write_blocked', $write_blocked);

		Context::set('config', $config);
		Context::set('categories', $categories);
		Context::set('tpl_row', $tpl);
		Context::set('template_id', $template_id);
		Context::set('buttons_json_disp', $buttons_json_disp);
		$this->setTemplateFile('template_write');
	}

	function dispWp_kakao_notiAdminTemplateDetail()
	{
		$oModel = getModel('wp_kakao_noti');
		$template_id = trim((string)Context::get('template_id'));
		if($template_id === '')
		{
			Context::set('tpl_row', null);
			Context::set('tpl_raw', '');
			Context::set('tpl_content_display', '');
			Context::set('template_id', '');
			$this->setTemplateFile('template_detail');

			return;
		}
		$res = $oModel->solapiRequest('GET', '/kakao/v2/templates/' . rawurlencode($template_id));
		$row = null;
		if(!empty($res['ok']) && is_array($res['body']))
		{
			$row = (object)$res['body'];
		}
		$tpl_content_display = '';
		if($row && isset($row->content))
		{
			$tpl_content_display = htmlspecialchars($oModel->normalizeAlimtalkTemplateContentForDisplay((string)$row->content), ENT_QUOTES, 'UTF-8');
		}
		Context::set('tpl_row', $row);
		Context::set('tpl_content_display', $tpl_content_display);
		Context::set('tpl_raw', $row ? json_encode($row, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : (string)($res['raw'] ?? ''));
		Context::set('template_id', $template_id);
		$st = $row && isset($row->status) ? (string)$row->status : '';
		Context::set('tpl_status_label_ko', $oModel->getKakaoAlimtalkTemplateStatusLabelKo($st));
		Context::set('wkn_tpl_inspecting', $oModel->isKakaoAlimtalkTemplateInspecting($st));
		Context::set('wkn_tpl_release_dormant_allowed', strtoupper(trim($st)) === 'APPROVED');
		$this->setTemplateFile('template_detail');
	}
}
