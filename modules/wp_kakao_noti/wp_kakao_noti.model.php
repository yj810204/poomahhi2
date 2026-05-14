<?php
/**
 * @class wp_kakao_notiModel
 * @brief SOLAPI HTTP, 설정, 로그 조회
 */
class wp_kakao_notiModel extends wp_kakao_noti
{
	const SOLAPI_BASE = 'https://api.solapi.com';

	/**
	 * @brief 모듈 설정 (기본값 병합)
	 */
	function getModuleConfig()
	{
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('wp_kakao_noti');
		if(!$config || !is_object($config))
		{
			$config = new stdClass();
		}
		if(!isset($config->solapi_api_key)) $config->solapi_api_key = '';
		if(!isset($config->solapi_api_secret)) $config->solapi_api_secret = '';
		if(!isset($config->solapi_pf_id)) $config->solapi_pf_id = '';
		if(!isset($config->solapi_channel_id)) $config->solapi_channel_id = '';
		if(!isset($config->solapi_from)) $config->solapi_from = '';
		if(!isset($config->alimtalk_enabled)) $config->alimtalk_enabled = 'N';
		if(!isset($config->sms_fallback_enabled)) $config->sms_fallback_enabled = 'N';
		if(!isset($config->phone_source)) $config->phone_source = 'member';

		return $config;
	}

	/**
	 * @brief SOLAPI Authorization 헤더 (HMAC-SHA256)
	 */
	function buildSolapiAuthHeader()
	{
		$config = $this->getModuleConfig();

		return $this->buildSolapiAuthHeaderFor($config->solapi_api_key, $config->solapi_api_secret);
	}

	/**
	 * @brief 지정한 Key/Secret으로 Authorization 헤더 생성
	 */
	function buildSolapiAuthHeaderFor($apiKey, $apiSecret)
	{
		$apiKey = trim((string)$apiKey);
		$apiSecret = trim((string)$apiSecret);
		if($apiKey === '' || $apiSecret === '')
		{
			return '';
		}
		$dateTime = gmdate('Y-m-d\TH:i:s\Z');
		$salt = bin2hex(random_bytes(16));
		$signature = hash_hmac('sha256', $dateTime . $salt, $apiSecret);

		return 'HMAC-SHA256 apiKey=' . $apiKey . ', date=' . $dateTime . ', salt=' . $salt . ', signature=' . $signature;
	}

	/**
	 * @brief SOLAPI REST 호출
	 * @param array $credentials 선택. api_key, api_secret 키가 있으면 해당 값을 쓰고, 없는 항목은 모듈 설정값 사용
	 * @return array{ok:bool,status:int,body:mixed,raw:string}
	 */
	function solapiRequest($method, $path, $body = null, array $credentials = array())
	{
		$config = $this->getModuleConfig();
		$apiKey = array_key_exists('api_key', $credentials) ? trim((string)$credentials['api_key']) : trim((string)$config->solapi_api_key);
		$apiSecret = array_key_exists('api_secret', $credentials) ? trim((string)$credentials['api_secret']) : trim((string)$config->solapi_api_secret);
		$auth = $this->buildSolapiAuthHeaderFor($apiKey, $apiSecret);
		if($auth === '')
		{
			return array('ok' => false, 'status' => 0, 'body' => null, 'raw' => '', 'error' => 'no_auth');
		}

		$url = self::SOLAPI_BASE . $path;
		$headers = array(
			'Authorization' => $auth,
		);
		$methodUpper = strtoupper($method);
		$data = null;
		if($body !== null && $body !== '')
		{
			$data = is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE);
			$headers['Content-Type'] = 'application/json';
		}

		$httpSettings = array(
			'timeout' => 15,
		);

		try
		{
			$response = \Rhymix\Framework\HTTP::request($url, $methodUpper, $data, $headers, array(), $httpSettings);
			$status = (int)$response->getStatusCode();
			$raw = (string)$response->getBody()->getContents();
			$decoded = json_decode($raw, true);

			return array(
				'ok' => ($status >= 200 && $status < 300),
				'status' => $status,
				'body' => is_array($decoded) ? $decoded : $raw,
				'raw' => $raw,
			);
		}
		catch(\Throwable $e)
		{
			return array(
				'ok' => false,
				'status' => 0,
				'body' => null,
				'raw' => '',
				'error' => $e->getMessage(),
			);
		}
	}

	/**
	 * @brief 연결 테스트 (메시지 목록 1건 GET)
	 */
	function testSolapiConnection(array $credentials = array())
	{
		return $this->solapiRequest('GET', '/messages/v4/list?limit=1', null, $credentials);
	}

	/**
	 * @brief SOLAPI 연동 카카오톡 채널 목록 (GET /kakao/v2/channels, 응답 channelList)
	 * @param array $credentials api_key, api_secret 선택 (없으면 모듈 설정)
	 * @param int $limit 1~500 권장
	 * @param string $startKey 목록 페이징용 (응답 nextKey)
	 * @return array{ok:bool,status:int,channels:array,nextKey:string,startKey:string,error:string,body:mixed,raw:string}
	 */
	function fetchSolapiKakaoChannels(array $credentials = array(), $limit = 100, $startKey = '')
	{
		$limit = (int)$limit;
		if($limit < 1)
		{
			$limit = 100;
		}
		if($limit > 500)
		{
			$limit = 500;
		}
		$query = array('limit' => $limit);
		$startKey = trim((string)$startKey);
		if($startKey !== '')
		{
			$query['startKey'] = $startKey;
		}
		$path = '/kakao/v2/channels?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
		$res = $this->solapiRequest('GET', $path, null, $credentials);

		$out = array(
			'ok' => !empty($res['ok']),
			'status' => (int)($res['status'] ?? 0),
			'channels' => array(),
			'nextKey' => '',
			'startKey' => $startKey,
			'error' => trim((string)($res['error'] ?? '')),
			'body' => $res['body'] ?? null,
			'raw' => (string)($res['raw'] ?? ''),
		);

		if($out['error'] === 'no_auth')
		{
			$out['ok'] = false;

			return $out;
		}

		if(!$out['ok'] || !is_array($res['body']))
		{
			$out['ok'] = false;

			return $out;
		}

		$body = $res['body'];
		if(isset($body['nextKey']) && is_string($body['nextKey']))
		{
			$out['nextKey'] = $body['nextKey'];
		}
		$list = array();
		if(isset($body['channelList']) && is_array($body['channelList']))
		{
			$list = $body['channelList'];
		}
		elseif(isset($body['list']) && is_array($body['list']))
		{
			$list = $body['list'];
		}

		$channels = array();
		foreach($list as $row)
		{
			if(!is_array($row))
			{
				continue;
			}
			$ch = new stdClass();
			$ch->channelId = isset($row['channelId']) ? (string)$row['channelId'] : '';
			$ch->searchId = isset($row['searchId']) ? (string)$row['searchId'] : '';
			$ch->channelName = isset($row['channelName']) && $row['channelName'] !== null ? (string)$row['channelName'] : '';
			$ch->phoneNumber = isset($row['phoneNumber']) ? (string)$row['phoneNumber'] : '';
			$ch->isBrand = !empty($row['isBrand']);
			if($ch->channelId !== '')
			{
				$channels[] = $ch;
			}
		}
		$out['channels'] = $channels;
		$out['ok'] = true;

		return $out;
	}

	/**
	 * @brief 알림톡 템플릿 content 표시용 (문자열에 남은 리터럴 `\n` 등을 실제 줄바꿈으로)
	 */
	function normalizeAlimtalkTemplateContentForDisplay($content)
	{
		$s = str_replace(array("\r\n", "\r"), "\n", (string)$content);
		$s = str_replace('\\r\\n', "\n", $s);
		$s = str_replace('\\r', "\n", $s);
		$s = str_replace('\\n', "\n", $s);
		$s = str_replace('\\t', "\t", $s);

		return $s;
	}

	/**
	 * @brief SOLAPI 알림톡 템플릿 status 코드 한글 표기 (kakaoAlimtalkTemplateStatusSchema 기준)
	 */
	function getKakaoAlimtalkTemplateStatusLabelKo($status)
	{
		$s = strtoupper(trim((string)$status));
		$map = array(
			'PENDING' => '검수 대기',
			'INSPECTING' => '검수 중',
			'APPROVED' => '승인 완료',
			'REJECTED' => '검수 반려',
		);

		return isset($map[$s]) ? $map[$s] : ($s !== '' ? $status : '-');
	}

	/**
	 * @brief 카카오 검수 진행 중 여부 (INSPECTING)
	 */
	function isKakaoAlimtalkTemplateInspecting($status)
	{
		return strtoupper(trim((string)$status)) === 'INSPECTING';
	}

	/**
	 * SOLAPI 오류 응답을 화면·알림용으로 읽기 쉬운 한 줄/여러 줄 문자열로 변환
	 *
	 * @param array|string|null $body solapiRequest()['body']
	 * @param string $raw 원본 raw (body가 비배열일 때)
	 * @param int $httpStatus HTTP 상태 (선택, 설명에 덧붙임)
	 */
	function formatSolapiErrorForDisplay($body, $raw = '', $httpStatus = 0)
	{
		$raw = trim((string)$raw);
		if(is_string($body) && trim($body) !== '')
		{
			$d = json_decode($body, true);
			if(json_last_error() === JSON_ERROR_NONE && is_array($d))
			{
				$body = $d;
			}
			else
			{
				return $raw !== '' ? $raw : (string)$body;
			}
		}

		if(!is_array($body) || !isset($body['errorCode']))
		{
			if(is_array($body) && count($body))
			{
				return trim(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
			}

			return $raw !== '' ? $raw : ($httpStatus > 0 ? ('HTTP ' . $httpStatus) : 'SOLAPI 오류');
		}

		$lines = array();
		$lines[] = $this->_wknSolapiErrorCodeLabel((string)$body['errorCode']);

		$msg = null;
		if(isset($body['errorMessage']))
		{
			$msg = $body['errorMessage'];
		}
		elseif(isset($body['message']))
		{
			$msg = $body['message'];
		}

		foreach($this->_wknSolapiFlattenErrorMessages($msg) as $detail)
		{
			$h = $this->_wknSolapiHumanizeValidationDetail($detail);
			if($h !== '')
			{
				$lines[] = '· ' . $h;
			}
		}

		if(count($lines) < 2 && $raw !== '')
		{
			$lines[] = '· ' . trim($raw);
		}

		$out = implode("\n", $lines);
		if($httpStatus > 0)
		{
			$out .= "\n(HTTP " . $httpStatus . ')';
		}

		return $out;
	}

	private function _wknSolapiErrorCodeLabel($code)
	{
		$code = trim($code);
		$map = array(
			'ValidationError' => '[검증 오류] SOLAPI 입력 규칙에 맞지 않습니다.',
			'Unauthorized' => '[인증 오류] API Key 또는 Secret을 확인하세요.',
			'Forbidden' => '[권한 오류] 이 작업을 할 수 없습니다.',
			'NotFound' => '[없음] 요청한 리소스를 찾을 수 없습니다.',
		);

		return isset($map[$code]) ? $map[$code] : ('[오류 코드: ' . $code . ']');
	}

	private function _wknSolapiFlattenErrorMessages($msg)
	{
		$out = array();
		if($msg === null || $msg === '')
		{
			return $out;
		}
		if(is_array($msg))
		{
			$it = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($msg));
			foreach($it as $v)
			{
				if(is_string($v))
				{
					$t = trim($v);
					if($t !== '')
					{
						$out[] = $t;
					}
				}
			}

			return $out;
		}

		$s = trim((string)$msg);
		$d = json_decode($s, true);
		if(json_last_error() === JSON_ERROR_NONE && ($d !== null))
		{
			return $this->_wknSolapiFlattenErrorMessages($d);
		}
		if($s !== '')
		{
			$out[] = $s;
		}

		return $out;
	}

	private function _wknSolapiHumanizeValidationDetail($line)
	{
		$t = trim((string)$line);
		for($i = 0; $i < 4; $i++)
		{
			if(strpos($t, '[[') === 0)
			{
				$t = substr($t, 2);
			}
			if(strlen($t) >= 2 && substr($t, -2) === ']]')
			{
				$t = substr($t, 0, -2);
			}
			$t = trim($t, " \t\n\r\0\x0B[]");
			if(strpos($t, '[[') !== 0)
			{
				break;
			}
		}

		if(preg_match('/^"([^"]+)"\s+(.+)$/us', $t, $m))
		{
			$field = $m[1];
			$rest = trim($m[2]);
			$labels = array(
				'channelId' => '채널 ID (channelId)',
				'templateId' => '템플릿 ID (templateId)',
				'name' => '템플릿 이름 (name)',
				'content' => '내용 (content)',
				'categoryCode' => '카테고리 코드 (categoryCode)',
				'messageType' => 'messageType',
				'emphasizeType' => 'emphasizeType',
				'buttons' => 'buttons (JSON)',
				'pfId' => 'pfId',
			);
			$label = isset($labels[$field]) ? $labels[$field] : $field;

			return $label . ' — ' . $rest;
		}

		if(preg_match('/^"?([a-zA-Z0-9_]+)"?\s+(.+)$/us', $t, $m))
		{
			$field = $m[1];
			$rest = trim($m[2]);
			$labels = array(
				'channelId' => '채널 ID (channelId)',
				'templateId' => '템플릿 ID (templateId)',
				'name' => '템플릿 이름 (name)',
				'content' => '내용 (content)',
				'categoryCode' => '카테고리 코드 (categoryCode)',
				'messageType' => 'messageType',
				'emphasizeType' => 'emphasizeType',
				'buttons' => 'buttons (JSON)',
				'pfId' => 'pfId',
			);
			$label = isset($labels[$field]) ? $labels[$field] : $field;

			return $label . ' — ' . $rest;
		}

		return $t;
	}

	/**
	 * @brief 국내 휴대폰 번호 정규화 (01012345678)
	 */
	function normalizePhoneNumber($phone)
	{
		$phone = trim((string)$phone);
		if($phone === '')
		{
			return '';
		}
		$digits = preg_replace('/\D+/', '', $phone);
		if(strpos($phone, '+82') === 0 || (strlen($digits) >= 10 && substr($digits, 0, 2) === '82'))
		{
			if(substr($digits, 0, 2) === '82')
			{
				$digits = '0' . substr($digits, 2);
			}
		}
		if(strlen($digits) === 10 && substr($digits, 0, 1) === '1')
		{
			$digits = '0' . $digits;
		}
		if(strlen($digits) < 10 || strlen($digits) > 11)
		{
			return '';
		}

		return $digits;
	}

	/**
	 * @brief 회원 전화번호 (설정 phone_source 기준)
	 */
	function getPhoneForMember($member_srl)
	{
		$member_srl = (int)$member_srl;
		if($member_srl < 1)
		{
			return '';
		}
		$config = $this->getModuleConfig();
		$oMemberModel = getModel('member');
		$member = $oMemberModel->getMemberInfoByMemberSrl($member_srl);
		if(!$member)
		{
			return '';
		}
		if(isset($config->phone_source) && $config->phone_source === 'member')
		{
			$phone = '';
			if(!empty($member->phone_number))
			{
				$phone = (string)$member->phone_number;
			}
			elseif(!empty($member->cellphone))
			{
				$phone = (string)$member->cellphone;
			}

			return $this->normalizePhoneNumber($phone);
		}

		return '';
	}

	function insertSendLogRow($row)
	{
		if(!isset($row->log_srl) || !$row->log_srl)
		{
			$row->log_srl = getNextSequence();
		}
		if(!isset($row->regdate) || $row->regdate === '')
		{
			$row->regdate = date('YmdHis');
		}
		if(!isset($row->member_srl))
		{
			$row->member_srl = 0;
		}
		if(!isset($row->variables))
		{
			$row->variables = '';
		}
		if(!isset($row->solapi_message_id))
		{
			$row->solapi_message_id = '';
		}
		if(!isset($row->solapi_status))
		{
			$row->solapi_status = '';
		}
		if(!isset($row->error_message))
		{
			$row->error_message = '';
		}
		if(!isset($row->caller_module))
		{
			$row->caller_module = '';
		}

		return executeQuery('wp_kakao_noti.insertSendLog', $row);
	}

	function getSendLog($log_srl)
	{
		$args = new stdClass();
		$args->log_srl = (int)$log_srl;

		return executeQuery('wp_kakao_noti.getSendLog', $args);
	}

	function getSendLogList($args, $filter_status = null)
	{
		if(!isset($args->page))
		{
			$args->page = 1;
		}
		if(!isset($args->list_count))
		{
			$args->list_count = 20;
		}
		if(!isset($args->page_count))
		{
			$args->page_count = 10;
		}
		if(!isset($args->sort_index))
		{
			$args->sort_index = 'log.log_srl';
		}

		if($filter_status !== null && $filter_status !== '')
		{
			$args->filter_status = (string)$filter_status;

			return executeQueryArray('wp_kakao_noti.getSendLogListByStatus', $args);
		}

		return executeQueryArray('wp_kakao_noti.getSendLogList', $args);
	}
}
