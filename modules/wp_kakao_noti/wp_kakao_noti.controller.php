<?php
/**
 * @class wp_kakao_notiController
 * @brief 알림톡 발송 (범용 API)
 */
class wp_kakao_notiController extends wp_kakao_noti
{
	function init()
	{
	}

	/**
	 * @brief 카카오 variables 키를 SOLAPI 형식(#{이름})으로 통일
	 */
	function normalizeKakaoVariables($variables)
	{
		if(!is_array($variables))
		{
			return array();
		}
		$out = array();
		foreach($variables as $k => $v)
		{
			$k = (string)$k;
			if(strpos($k, '#{') !== 0)
			{
				$k = '#{' . $k . '}';
			}
			$out[$k] = (string)$v;
		}

		return $out;
	}

	/**
	 * @brief 알림톡 1건 발송
	 *
	 * @param string $to_phone 수신 번호 (정규화 전 가능)
	 * @param string $templateId SOLAPI templateId
	 * @param array $variables 예: array('품앗이명' => '제목') 또는 array('#{품앗이명}' => '제목')
	 * @param array $buttons kakaoOptions.buttons (비우면 생략)
	 * @param array $options caller_module, member_srl, kakao_title, disable_sms
	 * @return BaseObject
	 */
	function sendAlimtalk($to_phone, $templateId, $variables = array(), $buttons = array(), $options = array())
	{
		$oModel = getModel('wp_kakao_noti');
		$config = $oModel->getModuleConfig();

		if(!is_array($options))
		{
			$options = array();
		}
		$caller_module = isset($options['caller_module']) ? trim((string)$options['caller_module']) : '';
		$member_srl = isset($options['member_srl']) ? (int)$options['member_srl'] : 0;
		$kakao_title = isset($options['kakao_title']) ? trim((string)$options['kakao_title']) : '';

		if($config->alimtalk_enabled !== 'Y')
		{
			return new BaseObject(-1, '알림톡 발송이 비활성화 상태입니다.');
		}

		$templateId = trim((string)$templateId);
		if($templateId === '')
		{
			return new BaseObject(-1, 'templateId가 없습니다.');
		}

		$pfId = trim((string)$config->solapi_pf_id);
		if($pfId === '')
		{
			return new BaseObject(-1, 'pfId가 설정되지 않았습니다.');
		}

		$to = $oModel->normalizePhoneNumber($to_phone);
		if($to === '')
		{
			return new BaseObject(-1, '수신 번호가 없거나 올바르지 않습니다.');
		}

		$sms_fallback = ($config->sms_fallback_enabled === 'Y');
		if(isset($options['disable_sms']) && $options['disable_sms'])
		{
			$sms_fallback = false;
		}

		$from = trim((string)$config->solapi_from);
		if($sms_fallback && $from === '')
		{
			return new BaseObject(-1, 'SMS 대체발송을 켠 경우 발신번호(solapi_from)가 필요합니다.');
		}

		$vars = $this->normalizeKakaoVariables($variables);

		$kakaoOptions = array(
			'pfId' => $pfId,
			'templateId' => $templateId,
			'variables' => $vars,
			'disableSms' => $sms_fallback ? false : true,
		);
		if($kakao_title !== '')
		{
			$kakaoOptions['title'] = $kakao_title;
		}
		if(is_array($buttons) && count($buttons) > 0)
		{
			$kakaoOptions['buttons'] = $buttons;
		}

		$msg = array(
			'to' => $to,
			'kakaoOptions' => $kakaoOptions,
		);
		if($from !== '')
		{
			$msg['from'] = $from;
		}

		$payload = array('messages' => array($msg));

		$log = new stdClass();
		$log->log_srl = getNextSequence();
		$log->template_id = $templateId;
		$log->to_phone = $to;
		$log->member_srl = $member_srl;
		$log->variables = json_encode($vars, JSON_UNESCAPED_UNICODE);
		$log->status = 'pending';
		$log->solapi_message_id = '';
		$log->solapi_status = '';
		$log->error_message = '';
		$log->caller_module = $caller_module;
		$log->regdate = date('YmdHis');
		$oModel->insertSendLogRow($log);

		$result = $oModel->solapiRequest('POST', '/messages/v4/send-many/detail', $payload);

		$upd = new stdClass();
		$upd->log_srl = $log->log_srl;

		if(!empty($result['error']))
		{
			$upd->status = 'failed';
			$upd->error_message = (string)$result['error'];
			executeQuery('wp_kakao_noti.updateSendLogStatus', $upd);

			return new BaseObject(-1, $upd->error_message);
		}

		if(!$result['ok'])
		{
			$err = $oModel->formatSolapiErrorForDisplay(is_array($result['body']) ? $result['body'] : null, (string)$result['raw'], (int)$result['status']);
			$upd->status = 'failed';
			$upd->error_message = function_exists('mb_substr') ? mb_substr($err, 0, 65000, 'UTF-8') : substr($err, 0, 65000);
			$upd->solapi_status = (string)$result['status'];
			executeQuery('wp_kakao_noti.updateSendLogStatus', $upd);

			return new BaseObject(-1, $upd->error_message ?: 'SOLAPI HTTP ' . $result['status']);
		}

		$body = $result['body'];
		$msgId = '';
		$st = 'sent';
		if(is_array($body))
		{
			if(isset($body['groupId']))
			{
				$msgId = (string)$body['groupId'];
			}
			elseif(isset($body['messageId']))
			{
				$msgId = (string)$body['messageId'];
			}
			elseif(isset($body['results']) && is_array($body['results']) && isset($body['results'][0]['messageId']))
			{
				$msgId = (string)$body['results'][0]['messageId'];
			}
			if(isset($body['failedMessageList']) && is_array($body['failedMessageList']) && count($body['failedMessageList']) > 0)
			{
				$st = 'failed';
				$upd->error_message = json_encode($body['failedMessageList'], JSON_UNESCAPED_UNICODE);
			}
		}

		$upd->status = $st;
		$upd->solapi_message_id = $msgId;
		$upd->solapi_status = (string)$result['status'];
		if(!isset($upd->error_message))
		{
			$upd->error_message = '';
		}
		executeQuery('wp_kakao_noti.updateSendLogStatus', $upd);

		if($st === 'failed')
		{
			return new BaseObject(-1, $upd->error_message ?: '발송 실패');
		}

		$out = new BaseObject();
		$out->add('log_srl', $log->log_srl);
		$out->add('solapi_message_id', $msgId);

		return $out;
	}

	/**
	 * @brief member_srl 기준 발송 (회원 전화번호)
	 */
	function sendAlimtalkToMember($member_srl, $templateId, $variables = array(), $buttons = array(), $options = array())
	{
		$member_srl = (int)$member_srl;
		if(!is_array($options))
		{
			$options = array();
		}
		$options['member_srl'] = $member_srl;

		$oModel = getModel('wp_kakao_noti');
		$phone = $oModel->getPhoneForMember($member_srl);
		if($phone === '')
		{
			$oMemberModel = getModel('member');
			$member = $oMemberModel->getMemberInfoByMemberSrl($member_srl);
			if(!$member)
			{
				return new BaseObject(-1, '회원을 찾을 수 없습니다.');
			}

			return new BaseObject(-1, '회원 전화번호가 없습니다.');
		}

		return $this->sendAlimtalk($phone, $templateId, $variables, $buttons, $options);
	}

	/**
	 * @brief 다건 발송 (각 항목: to_phone 또는 member_srl, variables 선택)
	 *
	 * @param array $recipients
	 * @return BaseObject 요약 success_count, fail_count
	 */
	function sendAlimtalkBulk($recipients, $templateId, $buttons = array(), $options = array())
	{
		if(!is_array($recipients) || !count($recipients))
		{
			return new BaseObject(-1, '수신 목록이 비어 있습니다.');
		}
		$ok = 0;
		$fail = 0;
		foreach($recipients as $row)
		{
			if(!is_array($row))
			{
				$row = (array)$row;
			}
			$vars = isset($row['variables']) && is_array($row['variables']) ? $row['variables'] : array();
			$opt = is_array($options) ? $options : array();
			if(isset($row['caller_module']))
			{
				$opt['caller_module'] = $row['caller_module'];
			}
			if(isset($row['member_srl']) && (int)$row['member_srl'] > 0)
			{
				$opt['member_srl'] = (int)$row['member_srl'];
				$r = $this->sendAlimtalkToMember((int)$row['member_srl'], $templateId, $vars, $buttons, $opt);
			}
			else
			{
				$to = isset($row['to_phone']) ? $row['to_phone'] : (isset($row['to']) ? $row['to'] : '');
				$r = $this->sendAlimtalk($to, $templateId, $vars, $buttons, $opt);
			}
			if($r->toBool())
			{
				$ok++;
			}
			else
			{
				$fail++;
			}
		}
		$out = new BaseObject();
		$out->add('success_count', $ok);
		$out->add('fail_count', $fail);

		return $out;
	}
}
