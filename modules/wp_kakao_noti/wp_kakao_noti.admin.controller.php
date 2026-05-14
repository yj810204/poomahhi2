<?php
/**
 * @class wp_kakao_notiAdminController
 * @brief 관리자 컨트롤러
 */
class wp_kakao_notiAdminController extends wp_kakao_noti
{
	function init()
	{
	}

	/**
	 * 관리자 화면 URL (wp_usimstore: mid + module_srl 유지)
	 */
	protected function _wknAdminUrl($act, array $extra = array())
	{
		$params = array('', 'module', 'admin', 'mid', wp_kakao_noti::wknCurrentMid());
		$msrl = Context::get('module_srl');
		if($msrl !== null && $msrl !== '' && (int)$msrl > 0)
		{
			$params[] = 'module_srl';
			$params[] = (int)$msrl;
		}
		$params[] = 'act';
		$params[] = $act;
		foreach($extra as $k => $v)
		{
			if($v === null || $v === '')
			{
				continue;
			}
			$params[] = $k;
			$params[] = $v;
		}

		return call_user_func_array('getNotEncodedUrl', $params);
	}

	function procWp_kakao_notiAdminSaveConfig()
	{
		$oModuleController = getController('module');
		$args = Context::getRequestVars();
		$oModel = getModel('wp_kakao_noti');
		$prev = $oModel->getModuleConfig();

		$config = new stdClass();
		$config->solapi_api_key = isset($args->solapi_api_key) ? trim((string)$args->solapi_api_key) : '';
		$secret_in = isset($args->solapi_api_secret) ? trim((string)$args->solapi_api_secret) : '';
		$config->solapi_api_secret = ($secret_in !== '') ? $secret_in : (string)$prev->solapi_api_secret;
		$config->solapi_pf_id = isset($args->solapi_pf_id) ? trim((string)$args->solapi_pf_id) : '';
		$config->solapi_channel_id = isset($args->solapi_channel_id) ? trim((string)$args->solapi_channel_id) : '';
		$config->solapi_from = isset($args->solapi_from) ? preg_replace('/\D+/', '', (string)$args->solapi_from) : '';
		$config->alimtalk_enabled = !empty($args->alimtalk_enabled) && $args->alimtalk_enabled === 'Y' ? 'Y' : 'N';
		$config->sms_fallback_enabled = !empty($args->sms_fallback_enabled) && $args->sms_fallback_enabled === 'Y' ? 'Y' : 'N';
		$config->phone_source = isset($args->phone_source) && $args->phone_source === 'member' ? 'member' : 'member';

		$output = $oModuleController->insertModuleConfig('wp_kakao_noti', $config);
		if(!$output->toBool()) return $output;

		$this->setMessage('저장되었습니다.');
		$this->setRedirectUrl($this->_wknAdminUrl('dispWp_kakao_notiAdminConfig'));

		return new BaseObject();
	}

	function procWp_kakao_notiAdminTestSend()
	{
		$to = trim((string)Context::get('test_to'));
		$templateId = trim((string)Context::get('test_template_id'));
		$vars_raw = trim((string)Context::get('test_variables'));
		$variables = array();
		if($vars_raw !== '')
		{
			$decoded = json_decode($vars_raw, true);
			if(is_array($decoded))
			{
				$variables = $decoded;
			}
		}

		$oController = getController('wp_kakao_noti');
		$output = $oController->sendAlimtalk($to, $templateId, $variables, array(), array(
			'caller_module' => 'wp_kakao_noti_test',
			'disable_sms' => true,
		));
		if(!$output->toBool()) return $output;

		$this->setMessage('발송 요청이 접수되었습니다.');
		$this->setRedirectUrl($this->_wknAdminUrl('dispWp_kakao_notiAdminConfig'));

		return new BaseObject();
	}

	function procWp_kakao_notiAdminSaveTemplate()
	{
		$oModel = getModel('wp_kakao_noti');
		$config = $oModel->getModuleConfig();
		$channelId = trim((string)$config->solapi_channel_id);
		if($channelId === '')
		{
			return new BaseObject(-1, '템플릿 등록을 위해 설정에 채널 ID를 입력하세요.');
		}

		$template_id = trim((string)Context::get('template_id'));
		$name = trim((string)Context::get('tpl_name'));
		$content = trim((string)Context::get('tpl_content'));
		$categoryCode = trim((string)Context::get('category_code'));
		$messageType = trim((string)Context::get('message_type')) ?: 'BA';
		$emphasizeType = trim((string)Context::get('emphasize_type')) ?: 'NONE';
		$buttons_json = trim((string)Context::get('buttons_json'));

		if($name === '' || $content === '' || $categoryCode === '')
		{
			return new BaseObject(-1, '이름, 내용, 카테고리 코드는 필수입니다.');
		}

		$buttons = array();
		if($buttons_json !== '')
		{
			$b = json_decode($buttons_json, true);
			if(is_array($b))
			{
				$buttons = $b;
			}
			else
			{
				return new BaseObject(-1, '버튼 JSON 형식이 올바르지 않습니다.');
			}
		}

		if($template_id !== '')
		{
			$cur = $oModel->solapiRequest('GET', '/kakao/v2/templates/' . rawurlencode($template_id));
			if(empty($cur['ok']) || !is_array($cur['body']))
			{
				$msg = $oModel->formatSolapiErrorForDisplay(is_array($cur['body']) ? $cur['body'] : null, (string)($cur['raw'] ?? ''), (int)($cur['status'] ?? 0));

				return new BaseObject(-1, $msg !== '' ? $msg : '템플릿 정보를 불러오지 못했습니다.');
			}
			if($oModel->isKakaoAlimtalkTemplateInspecting((string)($cur['body']['status'] ?? '')))
			{
				return new BaseObject(-1, '검수 중인 템플릿은 수정할 수 없습니다. 검수가 끝나거나 검수 요청을 취소한 뒤 다시 시도하세요.');
			}
		}

		$body = array(
			'name' => $name,
			'content' => $content,
			'categoryCode' => $categoryCode,
			'messageType' => $messageType,
			'emphasizeType' => $emphasizeType,
			'buttons' => $buttons,
			'quickReplies' => array(),
		);
		if($template_id === '')
		{
			$body['channelId'] = $channelId;
			$res = $oModel->solapiRequest('POST', '/kakao/v2/templates', $body);
		}
		else
		{
			$res = $oModel->solapiRequest('PUT', '/kakao/v2/templates/' . rawurlencode($template_id), $body);
		}

		if(!empty($res['error']))
		{
			return new BaseObject(-1, $res['error']);
		}
		if(empty($res['ok']))
		{
			$msg = $oModel->formatSolapiErrorForDisplay(is_array($res['body']) ? $res['body'] : null, (string)$res['raw'], (int)$res['status']);

			return new BaseObject(-1, $msg ?: 'HTTP ' . (int)$res['status']);
		}

		$this->setMessage('저장되었습니다.');
		$newId = '';
		if(is_array($res['body']))
		{
			foreach(array('templateId', 'template_id', 'id') as $_k)
			{
				if(isset($res['body'][$_k]) && (string)$res['body'][$_k] !== '')
				{
					$newId = (string)$res['body'][$_k];
					break;
				}
			}
		}

		$redirectTemplateId = trim((string)Context::get('template_id'));
		if($redirectTemplateId === '' && $newId !== '')
		{
			$redirectTemplateId = $newId;
		}
		if($redirectTemplateId !== '')
		{
			$this->setRedirectUrl($this->_wknAdminUrl('dispWp_kakao_notiAdminTemplateDetail', array('template_id' => $redirectTemplateId)));
		}
		else
		{
			$this->setRedirectUrl($this->_wknAdminUrl('dispWp_kakao_notiAdminTemplateList'));
		}

		return new BaseObject();
	}

	function procWp_kakao_notiAdminDeleteTemplate()
	{
		$oModel = getModel('wp_kakao_noti');
		$template_id = trim((string)Context::get('template_id'));
		if($template_id === '')
		{
			return new BaseObject(-1, 'template_id가 없습니다.');
		}
		$res = $oModel->solapiRequest('DELETE', '/kakao/v2/templates/' . rawurlencode($template_id));
		if(!empty($res['error']))
		{
			return new BaseObject(-1, $res['error']);
		}
		if(empty($res['ok']))
		{
			$msg = $oModel->formatSolapiErrorForDisplay(is_array($res['body']) ? $res['body'] : null, (string)$res['raw'], (int)$res['status']);

			return new BaseObject(-1, $msg ?: 'HTTP ' . (int)$res['status']);
		}

		$this->setMessage('삭제되었습니다.');
		$this->setRedirectUrl($this->_wknAdminUrl('dispWp_kakao_notiAdminTemplateList'));

		return new BaseObject();
	}

	function procWp_kakao_notiAdminRequestInspection()
	{
		$oModel = getModel('wp_kakao_noti');
		$template_id = trim((string)Context::get('template_id'));
		$comment = trim((string)Context::get('inspection_comment'));
		if($template_id === '')
		{
			return new BaseObject(-1, 'template_id가 없습니다.');
		}
		$res = $oModel->solapiRequest('PUT', '/kakao/v2/templates/' . rawurlencode($template_id) . '/inspection', array('comment' => $comment));
		if(!empty($res['error']))
		{
			return new BaseObject(-1, $res['error']);
		}
		if(empty($res['ok']))
		{
			$msg = $oModel->formatSolapiErrorForDisplay(is_array($res['body']) ? $res['body'] : null, (string)$res['raw'], (int)$res['status']);

			return new BaseObject(-1, $msg ?: 'HTTP ' . (int)$res['status']);
		}

		$this->setMessage('검수 요청되었습니다.');
		$this->setRedirectUrl($this->_wknAdminUrl('dispWp_kakao_notiAdminTemplateDetail', array('template_id' => $template_id)));

		return new BaseObject();
	}

	function procWp_kakao_notiAdminCancelInspection()
	{
		$oModel = getModel('wp_kakao_noti');
		$template_id = trim((string)Context::get('template_id'));
		if($template_id === '')
		{
			return new BaseObject(-1, 'template_id가 없습니다.');
		}
		$res = $oModel->solapiRequest('PUT', '/kakao/v2/templates/' . rawurlencode($template_id) . '/inspection/cancel', new stdClass());
		if(!empty($res['error']))
		{
			return new BaseObject(-1, $res['error']);
		}
		if(empty($res['ok']))
		{
			$msg = $oModel->formatSolapiErrorForDisplay(is_array($res['body']) ? $res['body'] : null, (string)$res['raw'], (int)$res['status']);

			return new BaseObject(-1, $msg ?: 'HTTP ' . (int)$res['status']);
		}

		$this->setMessage('검수 취소되었습니다.');
		$this->setRedirectUrl($this->_wknAdminUrl('dispWp_kakao_notiAdminTemplateDetail', array('template_id' => $template_id)));

		return new BaseObject();
	}

	function procWp_kakao_notiAdminReleaseDormant()
	{
		$oModel = getModel('wp_kakao_noti');
		$template_id = trim((string)Context::get('template_id'));
		if($template_id === '')
		{
			return new BaseObject(-1, 'template_id가 없습니다.');
		}
		$cur = $oModel->solapiRequest('GET', '/kakao/v2/templates/' . rawurlencode($template_id));
		if(empty($cur['ok']) || !is_array($cur['body']))
		{
			$msg = $oModel->formatSolapiErrorForDisplay(is_array($cur['body']) ? $cur['body'] : null, (string)($cur['raw'] ?? ''), (int)($cur['status'] ?? 0));

			return new BaseObject(-1, $msg !== '' ? $msg : '템플릿 정보를 불러오지 못했습니다.');
		}
		$curStatus = strtoupper(trim((string)($cur['body']['status'] ?? '')));
		if($curStatus !== 'APPROVED')
		{
			$label = $oModel->getKakaoAlimtalkTemplateStatusLabelKo($curStatus);

			return new BaseObject(-1, '휴면 해제는 승인 완료 상태의 템플릿에서만 요청할 수 있습니다. (현재: ' . $label . ')');
		}
		$res = $oModel->solapiRequest('POST', '/kakao/v2/templates/' . rawurlencode($template_id) . '/relese-dormant', null);
		if(!empty($res['error']))
		{
			return new BaseObject(-1, $res['error']);
		}
		if(empty($res['ok']))
		{
			$msg = $oModel->formatSolapiErrorForDisplay(is_array($res['body']) ? $res['body'] : null, (string)$res['raw'], (int)$res['status']);

			return new BaseObject(-1, $msg ?: 'HTTP ' . (int)$res['status']);
		}

		$this->setMessage('휴면 해제 요청이 처리되었습니다. 반영까지 시간이 걸릴 수 있으니 잠시 후 상세를 새로고침하세요.');
		$this->setRedirectUrl($this->_wknAdminUrl('dispWp_kakao_notiAdminTemplateDetail', array('template_id' => $template_id)));

		return new BaseObject();
	}

	function procWp_kakao_notiAdminResendFromLog()
	{
		$log_srl = (int)Context::get('log_srl');
		if($log_srl < 1)
		{
			return new BaseObject(-1, 'log_srl이 없습니다.');
		}
		$oModel = getModel('wp_kakao_noti');
		$out = $oModel->getSendLog($log_srl);
		if(!$out->toBool() || !$out->data)
		{
			return new BaseObject(-1, '로그를 찾을 수 없습니다.');
		}
		$row = $out->data;
		if(is_array($row))
		{
			$row = isset($row[0]) ? $row[0] : null;
		}
		if(!$row)
		{
			return new BaseObject(-1, '로그를 찾을 수 없습니다.');
		}
		$variables = array();
		if(!empty($row->variables))
		{
			$decoded = json_decode((string)$row->variables, true);
			if(is_array($decoded))
			{
				$variables = $decoded;
			}
		}

		$oController = getController('wp_kakao_noti');
		$r = $oController->sendAlimtalk((string)$row->to_phone, (string)$row->template_id, $variables, array(), array(
			'caller_module' => 'wp_kakao_noti_resend',
			'member_srl' => (int)$row->member_srl,
			'disable_sms' => true,
		));
		if(!$r->toBool()) return $r;

		$this->setMessage('재발송 요청이 접수되었습니다.');
		$this->setRedirectUrl($this->_wknAdminUrl('dispWp_kakao_notiAdminSendLog'));

		return new BaseObject();
	}
}
