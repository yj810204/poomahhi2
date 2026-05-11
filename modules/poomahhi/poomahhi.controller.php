<?php
/**
 * @class poomahhiController
 * @author WP
 * @brief 품앗이 모듈 프론트 컨트롤러
 */
class poomahhiController extends poomahhi
{
	var $product_type = 'product';
	var $primary_module_srl = 0;

	function init()
	{
		// mid 기반 product_type 자동 감지
		if($this->mid === 'local_poomahhi')
		{
			$this->product_type = 'local';
		}
		else
		{
			$this->product_type = 'product';
		}

		// 공유 데이터를 위해 메인 poomahhi module_srl 사용
		$oModuleModel = getModel('module');
		$main_module = $oModuleModel->getModuleInfoByMid('poomahhi');
		if($main_module && $main_module->module_srl)
		{
			$this->primary_module_srl = $main_module->module_srl;
		}
		else
		{
			$this->primary_module_srl = $this->module_srl;
		}
	}

	/**
	 * 트리거: moduleHandler.init (before)
	 * 사이트맵에서 전용 mid만 연결한 경우(index act 없음) 화면별 기본 액션을 부여한다.
	 */
	function triggerModuleHandlerInitBefore($oModuleHandler)
	{
		if(!$oModuleHandler || !is_object($oModuleHandler))
		{
			return;
		}
		if(strval($oModuleHandler->act) !== '')
		{
			return;
		}
		$mid = strval($oModuleHandler->mid);
		if($mid === '')
		{
			return;
		}
		$default_act = null;
		if($mid === 'poomahhi_business')
		{
			$default_act = 'dispPoomahhiCreatorDashboard';
		}
		if(!$default_act)
		{
			return;
		}
		$oModuleHandler->act = $default_act;
		Context::set('act', $default_act);
	}

	/**
	 * ncenterlite 알림 발송 (모듈 전역에서 이 메서드만 사용)
	 *
	 * Rhymix ncenterlite::sendNotification($from_member_srl, $to_member_srl, …)는
	 * 수신자 member_srl = $to, 연관(관리 목록의 "보낸 사람") target_member_srl = $from 으로 저장된다.
	 * 즉 "누가 알림함에 받는가"는 항상 두 번째 인자이다.
	 */
	function _sendNotification($from_member_srl, $to_member_srl, $message, $url = '', $target_srl = 0)
	{
		$oNcenterliteController = getController('ncenterlite');
		if(!$oNcenterliteController || !method_exists($oNcenterliteController, 'sendNotification'))
		{
			return;
		}
		$oNcenterliteController->sendNotification($from_member_srl, $to_member_srl, $message, $url, $target_srl);
	}

	/**
	 * 비즈니스 홈 배너 닫기: ncenter 알림은 읽음 처리, 그 외(신청·리뷰·마감)는 쿠키로 숨김 기록
	 */
	function procPoomahhiDismissBusinessDashboardBanner()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info || !$logged_info->member_srl)
		{
			return new BaseObject(-1, '로그인이 필요합니다.');
		}
		if(!$this->_isBusinessMember($logged_info))
		{
			return new BaseObject(-1, '권한이 없습니다.');
		}

		$notify = Context::get('notify');
		$notify = is_string($notify) ? trim($notify) : '';
		if($notify !== '')
		{
			$oNc = getController('ncenterlite');
			if($oNc && method_exists($oNc, 'updateNotifyRead'))
			{
				$output = $oNc->updateNotifyRead($notify, $logged_info->member_srl);
				if($output && !$output->toBool())
				{
					return $output;
				}
			}
			return new BaseObject();
		}

		$banner_type = Context::get('banner_type');
		$banner_type = is_string($banner_type) ? trim($banner_type) : '';
		$product_srl = (int)Context::get('product_srl');
		$application_srl = (int)Context::get('application_srl');

		$oModel = getModel('poomahhi');
		$key = null;

		if($banner_type === 'application')
		{
			if($product_srl < 1)
			{
				return new BaseObject(-1, '요청이 올바르지 않습니다.');
			}
			$product = $oModel->getProduct($product_srl);
			if(!$product || (int)$product->member_srl !== (int)$logged_info->member_srl)
			{
				return new BaseObject(-1, '요청이 올바르지 않습니다.');
			}
			$key = 'application:' . $product_srl;
		}
		elseif($banner_type === 'deadline')
		{
			if($product_srl < 1)
			{
				return new BaseObject(-1, '요청이 올바르지 않습니다.');
			}
			$product = $oModel->getProduct($product_srl);
			if(!$product || (int)$product->member_srl !== (int)$logged_info->member_srl)
			{
				return new BaseObject(-1, '요청이 올바르지 않습니다.');
			}
			$key = 'deadline:' . $product_srl;
		}
		elseif($banner_type === 'review')
		{
			if($application_srl < 1)
			{
				return new BaseObject(-1, '요청이 올바르지 않습니다.');
			}
			$app = $oModel->getApplication($application_srl);
			if(!$app)
			{
				return new BaseObject(-1, '요청이 올바르지 않습니다.');
			}
			$product = $oModel->getProduct($app->product_srl);
			if(!$product || (int)$product->member_srl !== (int)$logged_info->member_srl)
			{
				return new BaseObject(-1, '요청이 올바르지 않습니다.');
			}
			$key = 'review:' . $application_srl;
		}
		else
		{
			return new BaseObject(-1, '요청이 올바르지 않습니다.');
		}

		$map = $this->_poomahhiReadBannerDismissCookie();
		$map[$key] = 1;
		$payload = json_encode($map, JSON_UNESCAPED_UNICODE);
		if(strlen($payload) > 3900)
		{
			$map = array($key => 1);
			$payload = json_encode($map, JSON_UNESCAPED_UNICODE);
		}

		$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
		setcookie('pmh_bbdismiss', $payload, time() + 86400 * 60, '/', '', $secure, true);

		return new BaseObject();
	}

	function _poomahhiReadBannerDismissCookie()
	{
		if(empty($_COOKIE['pmh_bbdismiss']) || !is_string($_COOKIE['pmh_bbdismiss']))
		{
			return array();
		}
		$decoded = json_decode($_COOKIE['pmh_bbdismiss'], true);
		return is_array($decoded) ? $decoded : array();
	}

	/**
	 * @brief 트리거: 회원 가입 후 member_type 및 그룹 자동 배정
	 */
	function triggerAfterMemberInsert(&$obj)
	{
		$member_type = Context::get('member_type');
		if(!$member_type) return;

		$member_srl = $obj->member_srl;
		if(!$member_srl) return;

		$oMemberController = getController('member');

		// member_type 확장변수 DB 저장
		$oMemberController->updateMemberExtraVars($member_srl, array('member_type' => $member_type));

		// 그룹 배정 - 모듈 설정에서 그룹 SRL 가져오기
		$oModel = getModel('poomahhi');
		$config = $oModel->getModuleConfig();

		if($member_type === 'business' && !empty($config->business_group_srl))
		{
			$oMemberController->addMemberToGroup($member_srl, $config->business_group_srl);
		}
		elseif($member_type === 'general' && !empty($config->general_group_srl))
		{
			$oMemberController->addMemberToGroup($member_srl, $config->general_group_srl);
		}

		return;
	}

	/**
	 * @brief 트리거: moduleHandler.init (after) - 마이페이지 메뉴에 품앗이 항목 추가
	 * 코어 수정 없이 매 요청 초기화 시 실행되어 회원 메뉴에 반영됨.
	 */
	function triggerAddMemberMenu()
	{
		$oMemberController = getController('member');
		$oMemberController->addMemberMenu('dispPoomahhiWishlist', '관심 품앗이');
		$oMemberController->addMemberMenu('dispPoomahhiApplicationList', '내 신청 현황');
		$oMemberController->addMemberMenu('dispPoomahhiMyReviews', '내가 작성한 리뷰');
		$oMemberController->addMemberMenu('dispPoomahhiMyPoints', '내 포인트');

		// 품앗이 메뉴 링크용 mid (회원 스킨에서 사용) — 전용 mid(poomahhi_business)보다 기본 mid 우선
		if(!Context::get('poomahhi_mid'))
		{
			$oModuleModel = getModel('module');
			$mid_list = $oModuleModel->getMidList(null, array('mid', 'module'));
			$fallback_mid = null;
			$local_mid = null;
			foreach($mid_list as $m)
			{
				if($m->module !== 'poomahhi')
				{
					continue;
				}
				if($m->mid === 'poomahhi')
				{
					Context::set('poomahhi_mid', $m->mid);
					$fallback_mid = null;
					break;
				}
				if($m->mid === 'local_poomahhi')
				{
					$local_mid = $m->mid;
				}
				elseif($m->mid !== 'poomahhi_business' && $fallback_mid === null)
				{
					$fallback_mid = $m->mid;
				}
			}
			if(!Context::get('poomahhi_mid'))
			{
				Context::set('poomahhi_mid', $local_mid ?: $fallback_mid);
			}
		}
	}

	/**
	 * 회원 모듈「내가 쓴 글」화면에서만 document 목록 필터 적용 여부
	 */
	function _shouldFilterMemberOwnDocumentList()
	{
		if(!Context::get('is_logged'))
		{
			return false;
		}
		if(Context::get('act') !== 'dispMemberOwnDocument')
		{
			return false;
		}
		if(Context::get('module') === 'member')
		{
			return true;
		}
		$module_info = Context::get('current_module_info');
		if($module_info && isset($module_info->module) && $module_info->module === 'member')
		{
			return true;
		}
		return false;
	}

	/**
	 * 트리거: document.getDocumentList (before) — 내가 쓴 글을 설정된 두 게시판으로만 제한
	 */
	function triggerDocumentGetDocumentListBefore($obj)
	{
		if(!$this->_shouldFilterMemberOwnDocumentList() || !is_object($obj))
		{
			return;
		}
		$oModel = getModel('poomahhi');
		$config = $oModel->getModuleConfig();
		$mid_product = isset($config->own_document_product_mid) ? trim((string)$config->own_document_product_mid) : '';
		$mid_region = isset($config->own_document_region_mid) ? trim((string)$config->own_document_region_mid) : '';
		if($mid_product === '' || $mid_region === '')
		{
			return;
		}
		$oModuleModel = getModel('module');
		$info_product = $oModuleModel->getModuleInfoByMid($mid_product);
		$info_region = $oModuleModel->getModuleInfoByMid($mid_region);
		if(!$info_product || !$info_product->module_srl || !$info_region || !$info_region->module_srl)
		{
			return;
		}
		$srl_a = (int)$info_product->module_srl;
		$srl_b = (int)$info_region->module_srl;
		$obj->module_srl = ($srl_a === $srl_b) ? $srl_a : array($srl_a, $srl_b);
		Context::set('poomahhi_own_doc_filter_applied', 'Y');
	}

	/**
	 * 트리거: document.getDocumentList (after) — 내가 쓴 글 목록에 표시할「분류」문구 부착
	 * - 게시판 분류(category)가 아니라 문서 확장변수 extra_vars1 값을 사용함.
	 * - 회원 모듈은 목록 조회 시 load_extra_vars=false 이므로, 여기서 일괄 로드 후 값을 읽음.
	 * - 스킨: {$oDocument->poomahhi_category} (템플릿 파서 이슈로 짧은 프로퍼티명 사용)
	 */
	function triggerDocumentGetDocumentListAfter($output)
	{
		if(!$this->_shouldFilterMemberOwnDocumentList())
		{
			return;
		}
		if(Context::get('poomahhi_own_doc_filter_applied') === 'Y')
		{
			Context::set('poomahhi_own_doc_filter_applied', null);
		}
		if(!$output || (method_exists($output, 'toBool') && !$output->toBool()) || !isset($output->data))
		{
			return;
		}
		$data = $output->data;
		if(is_object($data))
		{
			$data = array($data);
		}
		if(!is_array($data) || !count($data))
		{
			return;
		}

		DocumentModel::setToAllDocumentExtraVars();

		$extra_eid = 'extra_vars1';

		foreach($data as $doc)
		{
			if(!is_object($doc) || !method_exists($doc, 'get'))
			{
				continue;
			}
			$doc_srl = isset($doc->document_srl) ? (int)$doc->document_srl : (int)$doc->get('document_srl');
			if(!$doc_srl)
			{
				continue;
			}

			$raw = null;
			if(method_exists($doc, 'getExtraEidValue'))
			{
				$raw = $doc->getExtraEidValue($extra_eid);
			}
			if($raw === null || $raw === '')
			{
				$raw = $doc->get($extra_eid);
			}

			if(is_array($raw))
			{
				$raw = implode(', ', $raw);
			}
			$display = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$raw)));
			$doc->poomahhi_category = ($display !== '') ? $display : '-';
		}
	}

	/**
	 * @brief 트리거: 회원가입 폼 출력 전 처리
	 * - member_type이 있으면 Context에 설정 (유형 선택 페이지에서 넘어온 경우)
	 * - member_type이 없으면 회원가입 유형 선택 페이지로 리다이렉트
	 */
	function triggerBeforeDispMemberSignUpForm(&$member_config)
	{
		$member_type = Context::get('member_type');
		if($member_type)
		{
			Context::set('member_type', $member_type);
			return;
		}

		// member_type 없이 접속 시 유형 선택 페이지로 리다이렉트
		$oModel = getModel('poomahhi');
		$config = $oModel->getModuleConfig();
		$signup_select_mid = isset($config->signup_select_mid) && $config->signup_select_mid
			? $config->signup_select_mid
			: null;
		if(!$signup_select_mid)
		{
			$oModuleModel = getModel('module');
			$mid_list = $oModuleModel->getMidList(null, array('mid', 'module'));
			foreach($mid_list as $m)
			{
				if($m->module === 'poomahhi')
				{
					$signup_select_mid = $m->mid;
					break;
				}
			}
		}
		if($signup_select_mid)
		{
			$redirect_url = getNotEncodedUrl('', 'mid', $signup_select_mid, 'act', 'dispPoomahhiSignupSelect');
			$oMemberView = getView('member');
			if($oMemberView && method_exists($oMemberView, 'setRedirectUrl'))
			{
				$oMemberView->setRedirectUrl($redirect_url);
				return new BaseObject(-1, 'redirect');
			}
		}
		return;
	}

	/**
	 * @brief 상품 등록
	 */
	function procPoomahhiInsertProduct()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return new BaseObject(-1, '로그인이 필요합니다.');

		// 비즈니스 회원 또는 관리자만
		if(!$this->_isBusinessMember($logged_info) && !$this->_isAdmin($logged_info))
		{
			return new BaseObject(-1, '권한이 없습니다.');
		}

		$args = Context::getRequestVars();

		// 뷰에서 미리 생성한 product_srl 사용 (에디터 upload_target_srl과 동일)
		if(!$args->product_srl)
		{
			$args->product_srl = getNextSequence();
		}
		$args->member_srl = $logged_info->member_srl;
		$args->module_srl = $this->primary_module_srl;
		if(!$args->status) $args->status = 'active';
		// mid 기반 product_type 자동 적용
		if(!$args->product_type) $args->product_type = $this->product_type;

		// 콘텐츠 접근 설정
		$args->content_access_type = in_array($args->content_access_type, array('public', 'private', 'paid')) ? $args->content_access_type : 'public';
		$args->point_cost = ($args->content_access_type === 'paid') ? max(0, (int)$args->point_cost) : 0;

		// 지역 타입일 경우 상품 전용 필드 초기화
		if($args->product_type === 'local')
		{
			$args->shopping_channel = '';
			$args->product_url = '';
		}
		else
		{
			// 상품 타입일 경우 지역 전용 필드 초기화
			$args->region_srl = 0;
			$args->contact = '';
			$args->zipcode = '';
			$args->address = '';
			$args->address_detail = '';
			$args->visit_info = '';
		}

		$this->_convertDateFields($args);

		$args->product_image = $this->_uploadProductImage($args->product_srl);

		$output = executeQuery('poomahhi.insertProduct', $args);
		if(!$output->toBool()) return $output;

		$this->_saveProductExtras($args->product_srl, $args->extra_template_srl);

		$oFileController = getController('file');
		$oFileController->setFilesValid($args->product_srl);

		$this->setMessage('등록되었습니다.');
		$this->add('product_srl', $args->product_srl);

		$returnUrl = getNotEncodedUrl('', 'mid', $this->mid, 'act', 'dispPoomahhiProductDetail', 'product_srl', $args->product_srl);
		$this->setRedirectUrl($returnUrl);
	}

	/**
	 * @brief 상품 수정
	 */
	function procPoomahhiUpdateProduct()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return new BaseObject(-1, '로그인이 필요합니다.');

		$args = Context::getRequestVars();
		$product_srl = $args->product_srl;
		if(!$product_srl) return new BaseObject(-1, '상품을 찾을 수 없습니다.');

		$oModel = getModel('poomahhi');
		$product = $oModel->getProduct($product_srl);
		if(!$product) return new BaseObject(-1, '상품을 찾을 수 없습니다.');

		// 본인 상품 또는 관리자만
		if($product->member_srl != $logged_info->member_srl && !$this->_isAdmin($logged_info))
		{
			return new BaseObject(-1, '권한이 없습니다.');
		}

		// product_type 유지 (수정 시에도 기존 타입 유지)
		if(!$args->product_type) $args->product_type = $product->product_type ?: 'product';

		// 콘텐츠 접근 설정
		$args->content_access_type = in_array($args->content_access_type, array('public', 'private', 'paid')) ? $args->content_access_type : 'public';
		$args->point_cost = ($args->content_access_type === 'paid') ? max(0, (int)$args->point_cost) : 0;

		// 지역 타입일 경우 상품 전용 필드 초기화
		if($args->product_type === 'local')
		{
			$args->shopping_channel = '';
			$args->product_url = '';
		}
		else
		{
			$args->region_srl = 0;
			$args->contact = '';
			$args->zipcode = '';
			$args->address = '';
			$args->address_detail = '';
			$args->visit_info = '';
		}

		$this->_convertDateFields($args);

		// 대표이미지 삭제 요청
		if($args->delete_product_image === 'Y')
		{
			$this->_deleteProductImage($product->product_image);
			$args->product_image = '';
		}
		else
		{
			// 새 대표이미지 업로드
			$new_image = $this->_uploadProductImage($product_srl);
			if($new_image)
			{
				// 기존 이미지 삭제
				$this->_deleteProductImage($product->product_image);
				$args->product_image = $new_image;
			}
		}

		$args->last_update = date('YmdHis');
		$output = executeQuery('poomahhi.updateProduct', $args);
		if(!$output->toBool()) return $output;

		// 확장변수 갱신 (선택된 템플릿 기준)
		$this->_saveProductExtras($product_srl, $args->extra_template_srl);

		// 에디터 첨부파일 유효화 (새로 업로드된 파일 is_valid = 'N' → 'Y')
		$oFileController = getController('file');
		$oFileController->setFilesValid($product_srl);

		$this->setMessage('수정되었습니다.');
		$returnUrl = getNotEncodedUrl('', 'mid', $this->mid, 'act', 'dispPoomahhiProductDetail', 'product_srl', $product_srl);
		$this->setRedirectUrl($returnUrl);
	}

	/**
	 * @brief 상품 삭제
	 */
	function procPoomahhiDeleteProduct()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return new BaseObject(-1, '로그인이 필요합니다.');

		$product_srl = Context::get('product_srl');
		if(!$product_srl) return new BaseObject(-1, '상품을 찾을 수 없습니다.');

		$oModel = getModel('poomahhi');
		$product = $oModel->getProduct($product_srl);
		if(!$product) return new BaseObject(-1, '상품을 찾을 수 없습니다.');

		if($product->member_srl != $logged_info->member_srl && !$this->_isAdmin($logged_info))
		{
			return new BaseObject(-1, '권한이 없습니다.');
		}

		// 대표이미지 삭제
		$this->_deleteProductImage($product->product_image);

		// 상품 삭제
		$output = executeQuery('poomahhi.deleteProduct', (object)array('product_srl' => $product_srl));
		if(!$output->toBool()) return $output;

		// 확장변수 삭제
		executeQuery('poomahhi.deleteProductExtras', (object)array('product_srl' => $product_srl));

		// 에디터 첨부 파일 삭제
		$oFileController = getController('file');
		$oFileController->deleteFiles($product_srl);

		$this->setMessage('삭제되었습니다.');
		$returnUrl = getNotEncodedUrl('', 'mid', $this->mid, 'act', 'dispPoomahhiProductList');
		$this->setRedirectUrl($returnUrl);
	}

	function procPoomahhiInsertApplication()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return new BaseObject(-1, '로그인이 필요합니다.');

		$args = Context::getRequestVars();
		if(!$args->product_srl) return new BaseObject(-1, '상품을 찾을 수 없습니다.');

		$oModel = getModel('poomahhi');
		$product = $oModel->getProduct($args->product_srl);
		if(!$product) return new BaseObject(-1, '상품을 찾을 수 없습니다.');

		// 신청 마감일 검사 (apply_end_date 우선, 없으면 deadline_date)
		$deadline_source = $product->apply_end_date ?: $product->deadline_date;
		if($deadline_source)
		{
			$deadline_ymdhis = (strlen($deadline_source) === 8) ? $deadline_source . '235959' : $deadline_source;
			if(date('YmdHis') > $deadline_ymdhis)
			{
				return new BaseObject(-1, '신청 마감일이 지나 신청할 수 없습니다.');
			}
		}

		$existing_count = $oModel->getActiveApplicationCount($args->product_srl, $logged_info->member_srl);
		if($existing_count > 0) return new BaseObject(-1, '이미 신청한 품앗이입니다.');

		if($product->max_applicants > 0)
		{
			$total_count = $oModel->getApplicationCount($args->product_srl);
			if($total_count >= $product->max_applicants)
			{
				return new BaseObject(-1, '최대 신청 인원을 초과했습니다.');
			}
		}

		if($args->privacy_agreed !== 'Y') return new BaseObject(-1, '개인정보 수집이용에 동의해 주세요.');
		if(!$args->applicant_comment) return new BaseObject(-1, '신청자 한마디를 입력해 주세요.');
		if(!$args->applicant_name) return new BaseObject(-1, '이름을 입력해 주세요.');
		if(!$args->birth_date) return new BaseObject(-1, '출생년도를 입력해 주세요.');
		if(!$args->gender) return new BaseObject(-1, '성별을 선택해 주세요.');
		if(!$args->phone) return new BaseObject(-1, '연락처를 입력해 주세요.');

		if($product->product_type === 'local')
		{
			if(!$args->applicant_contact_name) return new BaseObject(-1, '이름을 입력해 주세요.');
			if(!$args->applicant_contact_phone) return new BaseObject(-1, '연락처를 입력해 주세요.');
			if(!$args->applicant_zipcode || !$args->applicant_address)
				return new BaseObject(-1, '주소를 입력해 주세요. (주소 검색을 이용해 주세요.)');
		}

		$insert_args = new stdClass();
		$insert_args->application_srl = getNextSequence();
		$insert_args->product_srl = $args->product_srl;
		$insert_args->member_srl = $logged_info->member_srl;
		$insert_args->status = 'applied';
		$insert_args->applicant_comment = $args->applicant_comment;
		$insert_args->applicant_name = $args->applicant_name;
		$insert_args->birth_date = $args->birth_date;
		$insert_args->gender = $args->gender;
		$insert_args->phone = $args->phone;
		$insert_args->privacy_agreed = 'Y';
		$insert_args->regdate = date('YmdHis');
		if($product->product_type === 'local')
		{
			$insert_args->applicant_contact_name = $args->applicant_contact_name ?: '';
			$insert_args->applicant_contact_phone = $args->applicant_contact_phone ?: '';
			$insert_args->applicant_zipcode = $args->applicant_zipcode ?: '';
			$insert_args->applicant_address = $args->applicant_address ?: '';
			$insert_args->applicant_address_detail = $args->applicant_address_detail ?: '';
		}
		else
		{
			$insert_args->applicant_contact_name = '';
			$insert_args->applicant_contact_phone = '';
			$insert_args->applicant_zipcode = '';
			$insert_args->applicant_address = '';
			$insert_args->applicant_address_detail = '';
		}

		$config = $oModel->getModuleConfig();
		if($config->review_deadline_days)
		{
			$deadline = new DateTime();
			$deadline->modify('+' . (int)$config->review_deadline_days . ' days');
			$insert_args->deadline = $deadline->format('YmdHis');
		}

		$output = executeQuery('poomahhi.insertApplication', $insert_args);
		if(!$output->toBool()) return $output;

		$manage_url = getNotEncodedUrl('', 'mid', $this->mid, 'act', 'dispPoomahhiApplicationManage', 'product_srl', $product->product_srl);
		$tpl = isset($config->noti_tpl_new_application) ? trim((string)$config->noti_tpl_new_application) : '';
		if($tpl !== '')
		{
			$noti_msg = $oModel->replacePoomahhiNotificationTemplate($tpl, array(
				'product_title' => (string)$product->title,
				'applicant_nick' => isset($logged_info->nick_name) ? (string)$logged_info->nick_name : '',
				'applicant_user_id' => isset($logged_info->user_id) ? (string)$logged_info->user_id : '',
			));
		}
		else
		{
			$noti_msg = sprintf('[%s] 품앗이에 새로운 품앗이 신청이 왔습니다.', $product->title);
		}
		// ncenter: 수신=개설자(product), 발신=신청자(신규 신청 알림)
		$this->_sendNotification($logged_info->member_srl, $product->member_srl, $noti_msg, $manage_url, $insert_args->application_srl);

		$this->setMessage('등록되었습니다.');

		$returnUrl = Context::get('success_return_url');
		if(!$returnUrl) $returnUrl = getNotEncodedUrl('', 'mid', $this->mid, 'act', 'dispPoomahhiApplicationList');
		$this->setRedirectUrl($returnUrl);
	}

	/**
	 * @brief 신청 상태 변경 (어드민 또는 해당 품앗이 개설자만)
	 */
	function procPoomahhiUpdateApplicationStatus()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return new BaseObject(-1, '로그인이 필요합니다.');

		$application_srl = Context::get('application_srl');
		$new_status = Context::get('status');
		if(!$application_srl || !$new_status) return new BaseObject(-1, '잘못된 요청입니다.');

		$oModel = getModel('poomahhi');
		$application = $oModel->getApplication($application_srl);
		if(!$application) return new BaseObject(-1, '신청 정보를 찾을 수 없습니다.');

		// 권한 확인: 관리자 또는 해당 상품 개설자
		$product = $oModel->getProduct($application->product_srl);
		if(!$product) return new BaseObject(-1, '상품을 찾을 수 없습니다.');

		if($product->member_srl != $logged_info->member_srl && !$this->_isAdmin($logged_info))
		{
			return new BaseObject(-1, '권한이 없습니다.');
		}

		// 유효한 상태값 확인
		$valid_statuses = array('applied', 'selected', 'under_review', 'revision_requested', 'completed', 'rejected', 'cancelled');
		if(!in_array($new_status, $valid_statuses))
		{
			return new BaseObject(-1, '잘못된 요청입니다.');
		}

		$null = new \Rhymix\Framework\Parsers\DBQuery\NullValue;

		$args = new stdClass();
		$args->application_srl = $application_srl;
		$args->status = $new_status;
		$args->admin_memo = Context::get('admin_memo');
		$args->last_update = date('YmdHis');

		if($new_status === 'rejected')
		{
			$args->rejection_reason = trim((string)Context::get('rejection_reason'));
			$args->rejection_detail = trim((string)Context::get('rejection_detail'));
			$args->revision_request_content = $null;
			if($args->rejection_reason === '')
			{
				return new BaseObject(-1, '미선정 사유를 선택해 주세요.');
			}
		}
		elseif($new_status === 'revision_requested')
		{
			$args->revision_request_content = trim((string)Context::get('revision_request_content'));
			$args->rejection_reason = $null;
			$args->rejection_detail = $null;
			if($args->revision_request_content === '')
			{
				return new BaseObject(-1, '수정 요청 내용을 입력해 주세요.');
			}
		}
		else
		{
			$args->rejection_reason = $null;
			$args->rejection_detail = $null;
			$args->revision_request_content = $null;
		}

		$output = executeQuery('poomahhi.updateApplicationStatus', $args);
		if(!$output->toBool()) return $output;

		$oMemberModel = getModel('member');
		$applicant_member = $oMemberModel->getMemberInfoByMemberSrl($application->member_srl);
		$applicant_nick = $applicant_member && $applicant_member->nick_name ? $applicant_member->nick_name : '회원';
		$detail_url = getNotEncodedUrl('', 'mid', $this->mid, 'act', 'dispPoomahhiApplicationDetail', 'application_srl', $application_srl);

		// ncenter: 수신=신청자, 발신=개설자(개설자가 상태를 바꿀 때 신청자에게 통지)
		if($new_status === 'selected')
		{
			$noti_msg = sprintf('[%s] 품앗이에 선정되었습니다.', $product->title);
			$this->_sendNotification($product->member_srl, $application->member_srl, $noti_msg, $detail_url, $application_srl);
		}
		elseif($new_status === 'rejected')
		{
			$noti_msg = sprintf('[%s] 품앗이에 미선정되었습니다.', $product->title);
			$this->_sendNotification($product->member_srl, $application->member_srl, $noti_msg, $detail_url, $application_srl);
		}
		elseif($new_status === 'revision_requested')
		{
			$applicant_user_id = $applicant_member && !empty($applicant_member->user_id) ? (string)$applicant_member->user_id : '';
			$cfg = $oModel->getModuleConfig();
			$tpl_rev = isset($cfg->noti_tpl_revision_requested) ? trim((string)$cfg->noti_tpl_revision_requested) : '';
			if($tpl_rev !== '')
			{
				$noti_msg = $oModel->replacePoomahhiNotificationTemplate($tpl_rev, array(
					'product_title' => (string)$product->title,
					'applicant_nick' => (string)$applicant_nick,
					'applicant_user_id' => $applicant_user_id,
				));
			}
			else
			{
				$noti_msg = sprintf('%s님의 참여 인증 제출이 수정요청되었습니다.', $applicant_nick);
			}
			$this->_sendNotification($product->member_srl, $application->member_srl, $noti_msg, $detail_url, $application_srl);
		}
		elseif($new_status === 'completed')
		{
			$noti_msg = sprintf('%s님의 참여 인증 제출이 완료되었습니다.', $applicant_nick);
			$this->_sendNotification($product->member_srl, $application->member_srl, $noti_msg, $detail_url, $application_srl);
		}

		$this->setMessage('수정되었습니다.');

		$returnUrl = Context::get('success_return_url');
		if(!$returnUrl) $returnUrl = getNotEncodedUrl('', 'mid', $this->mid, 'act', 'dispPoomahhiApplicationManage', 'product_srl', $application->product_srl);
		$this->setRedirectUrl($returnUrl);
	}

	/**
	 * @brief 리뷰 통과 (검수중 상태에서 completed로 변경, 품앗이 개설자 또는 관리자만)
	 */
	function procPoomahhiApproveReview()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return new BaseObject(-1, '로그인이 필요합니다.');

		$application_srl = Context::get('application_srl');
		if(!$application_srl) return new BaseObject(-1, '잘못된 요청입니다.');

		$oModel = getModel('poomahhi');
		$application = $oModel->getApplication($application_srl);
		if(!$application) return new BaseObject(-1, '신청 정보를 찾을 수 없습니다.');

		$product = $oModel->getProduct($application->product_srl);
		if(!$product) return new BaseObject(-1, '상품을 찾을 수 없습니다.');

		if($product->member_srl != $logged_info->member_srl && !$this->_isAdmin($logged_info))
		{
			return new BaseObject(-1, '권한이 없습니다.');
		}

		if($application->status !== 'under_review')
		{
			return new BaseObject(-1, '적용할 수 없는 상태입니다.');
		}

		$review = $oModel->getReviewByApplication($application_srl);
		if(!$review) return new BaseObject(-1, '참여 인증(리뷰)이 없습니다.');

		$status_args = new stdClass();
		$status_args->application_srl = $application_srl;
		$status_args->status = 'completed';
		$status_args->last_update = date('YmdHis');
		$output = executeQuery('poomahhi.updateApplicationStatusLite', $status_args);
		if(!$output->toBool()) return $output;

		$detail_url = getNotEncodedUrl('', 'mid', $this->mid, 'act', 'dispPoomahhiApplicationDetail', 'application_srl', $application_srl);
		$noti_msg = sprintf('[%s] 참여인증이 승인되었습니다.', $product->title);
		// ncenter: 수신=신청자, 발신=개설자
		$this->_sendNotification($product->member_srl, $application->member_srl, $noti_msg, $detail_url, $application_srl);

		$this->setMessage('수정되었습니다.');
		$returnUrl = Context::get('success_return_url');
		if(!$returnUrl) $returnUrl = getNotEncodedUrl('', 'mid', $this->mid, 'act', 'dispPoomahhiApplicationManageDetail', 'application_srl', $application_srl);
		$this->setRedirectUrl($returnUrl);
	}

	/**
	 * @brief 신청 내역 완전 삭제 (DB 삭제)
	 * - 반려/취소된 건: 신청자 본인 또는 관리자
	 * - 그 외: 해당 품앗이 개설자 또는 관리자
	 * 삭제하면 해당 회원이 재신청 가능
	 */
	function procPoomahhiDeleteApplication()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return new BaseObject(-1, '로그인이 필요합니다.');

		$application_srl = Context::get('application_srl');
		if(!$application_srl) return new BaseObject(-1, '신청 정보를 찾을 수 없습니다.');

		$oModel = getModel('poomahhi');
		$application = $oModel->getApplication($application_srl);
		if(!$application) return new BaseObject(-1, '신청 정보를 찾을 수 없습니다.');

		$product = $oModel->getProduct($application->product_srl);
		if(!$product) return new BaseObject(-1, '상품을 찾을 수 없습니다.');

		$is_applicant = ($application->member_srl == $logged_info->member_srl);
		$is_product_owner = ($product->member_srl == $logged_info->member_srl);
		$is_rejected_or_cancelled = in_array($application->status, array('rejected', 'cancelled'), true);

		// 권한: 관리자 / 상품 개설자 / 또는 (반려·취소 건의 신청자 본인)
		if(!$this->_isAdmin($logged_info) && !$is_product_owner && !($is_applicant && $is_rejected_or_cancelled))
		{
			return new BaseObject(-1, '권한이 없습니다.');
		}

		if($oModel->isApplicationUnfulfilled($application))
		{
			$oModel->addUnfulfilledExtra($application->member_srl);
		}

		$args = new stdClass();
		$args->application_srl = $application_srl;
		$output = executeQuery('poomahhi.deleteApplication', $args);
		if(!$output->toBool()) return $output;

		$this->setMessage('삭제되었습니다.');

		$returnUrl = Context::get('success_return_url');
		if(!$returnUrl)
		{
			if($is_applicant && !$is_product_owner)
				$returnUrl = getNotEncodedUrl('', 'mid', $this->mid, 'act', 'dispPoomahhiApplicationList');
			else
				$returnUrl = getNotEncodedUrl('', 'mid', $this->mid, 'act', 'dispPoomahhiApplicationManage', 'product_srl', $application->product_srl);
		}
		$this->setRedirectUrl($returnUrl);
	}

	/**
	 * @brief 리뷰 등록
	 */
	function procPoomahhiInsertReview()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return new BaseObject(-1, '로그인이 필요합니다.');

		$application_srl = Context::get('application_srl');
		if(!$application_srl) return new BaseObject(-1, '신청 정보를 찾을 수 없습니다.');

		$oModel = getModel('poomahhi');
		$application = $oModel->getApplication($application_srl);
		if(!$application) return new BaseObject(-1, '신청 정보를 찾을 수 없습니다.');

		// 본인 신청 건만 리뷰 가능
		if($application->member_srl != $logged_info->member_srl)
		{
			return new BaseObject(-1, '권한이 없습니다.');
		}

		// 선정(selected) 또는 수정요청(revision_requested) 상태만 리뷰 가능
		if(!in_array($application->status, array('selected', 'under_review', 'revision_requested')))
		{
			return new BaseObject(-1, '리뷰를 등록할 수 없는 상태입니다.');
		}

		// 이미 리뷰 존재 확인
		$existing_review = $oModel->getReviewByApplication($application_srl);
		if($existing_review) return new BaseObject(-1, '이미 리뷰를 등록했습니다.');

		$score = (int)Context::get('score');
		if($score < 1 || $score > 5) $score = 5;

		// 폼에서 전달한 upload_target_srl을 review_srl로 사용 (파일 첨부 연동)
		$review_srl = (int)Context::get('upload_target_srl');
		if($review_srl <= 0) $review_srl = getNextSequence();

		$draft = $oModel->getReviewDraftByApplication($application_srl);
		$new_paths_by_slot = $this->_saveReviewAttachmentsForReview($review_srl);
		$merged = $this->_buildReviewAttachmentPathsFromDraftAndNew($review_srl, $application_srl, $new_paths_by_slot, $draft);

		$args = new stdClass();
		$args->review_srl = $review_srl;
		$args->application_srl = $application_srl;
		$args->product_srl = $application->product_srl;
		$args->member_srl = $logged_info->member_srl;
		$args->score = $score;
		$args->content = trim((string)Context::get('content'));
		$args->certification_attachment_paths = isset($merged['certification']) ? $merged['certification'] : '';
		$args->purchase_attachment_paths = isset($merged['purchase']) ? $merged['purchase'] : '';
		$args->regdate = date('YmdHis');
		$args->last_update = date('YmdHis');

		$output = executeQuery('poomahhi.insertReview', $args);
		if(!$output->toBool()) return $output;

		// 신청 상태를 under_review로 변경
		$status_args = new stdClass();
		$status_args->application_srl = $application_srl;
		$status_args->status = 'under_review';
		$status_args->last_update = date('YmdHis');
		executeQuery('poomahhi.updateApplicationStatusLite', $status_args);

		// 임시저장 데이터 및 서버 파일 삭제 (리뷰로 복사 완료 후)
		$del_draft = new stdClass();
		$del_draft->application_srl = $application_srl;
		executeQuery('poomahhi.deleteReviewDraftByApplication', $del_draft);
		$this->_deleteReviewDraftFolder($application_srl);

		$product = $oModel->getProduct($application->product_srl);
		if(!$product) return new BaseObject(-1, '상품을 찾을 수 없습니다.');

		$oMemberModel = getModel('member');
		$applicant_member = $oMemberModel->getMemberInfoByMemberSrl($logged_info->member_srl);
		$applicant_nick = $applicant_member && $applicant_member->nick_name ? $applicant_member->nick_name : '회원';
		$applicant_user_id = $applicant_member && !empty($applicant_member->user_id) ? (string)$applicant_member->user_id : '';
		$manage_url = getNotEncodedUrl('', 'mid', $this->mid, 'act', 'dispPoomahhiApplicationManage', 'product_srl', $product->product_srl);
		$cfg = $oModel->getModuleConfig();
		$tpl = isset($cfg->noti_tpl_review_submitted) ? trim((string)$cfg->noti_tpl_review_submitted) : '';
		if($tpl !== '')
		{
			$noti_msg = $oModel->replacePoomahhiNotificationTemplate($tpl, array(
				'product_title' => (string)$product->title,
				'applicant_nick' => (string)$applicant_nick,
				'applicant_user_id' => $applicant_user_id,
			));
		}
		else
		{
			$noti_msg = sprintf('[%s] %s님의 참여 인증 제출이 완료되었습니다.', $product->title, $applicant_nick);
		}
		// ncenter: 수신=개설자, 발신=신청자(참여 인증 제출 알림)
		$this->_sendNotification($logged_info->member_srl, $product->member_srl, $noti_msg, $manage_url, $application_srl);

		$this->setMessage('등록되었습니다.');
		$returnUrl = getNotEncodedUrl('', 'mid', $this->mid, 'act', 'dispPoomahhiApplicationList');
		$this->setRedirectUrl($returnUrl);
	}

	/**
	 * @brief 참여 인증(리뷰) 수정 (수정요청 상태, 신청자 본인만)
	 */
	function procPoomahhiUpdateReview()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return new BaseObject(-1, '로그인이 필요합니다.');

		$application_srl = (int)Context::get('application_srl');
		if(!$application_srl) return new BaseObject(-1, '신청 정보를 찾을 수 없습니다.');

		$oModel = getModel('poomahhi');
		$application = $oModel->getApplication($application_srl);
		if(!$application) return new BaseObject(-1, '신청 정보를 찾을 수 없습니다.');

		if($application->member_srl != $logged_info->member_srl)
			return new BaseObject(-1, '권한이 없습니다.');

		if($application->status !== 'revision_requested')
			return new BaseObject(-1, '수정요청 상태인 경우에만 참여 인증을 수정할 수 있습니다.');

		$review = $oModel->getReviewByApplication($application_srl);
		if(!$review) return new BaseObject(-1, '잘못된 요청입니다.');

		$score = (int)Context::get('score');
		if($score < 1 || $score > 5) $score = 5;

		$review_srl = $review->review_srl;
		$new_paths_by_slot = $this->_saveReviewAttachmentsForReview($review_srl);
		$merged = $this->_mergeReviewAttachmentPathsForUpdate($new_paths_by_slot, $review);
		$this->_deleteRemovedReviewFiles($review, $merged);

		$up = new stdClass();
		$up->review_srl = $review_srl;
		$up->score = $score;
		$up->content = trim((string)Context::get('content'));
		$up->certification_attachment_paths = $merged['certification'];
		$up->purchase_attachment_paths = $merged['purchase'];
		$up->last_update = date('YmdHis');

		$output = executeQuery('poomahhi.updateReview', $up);
		if(!$output->toBool()) return $output;

		$status_args = new stdClass();
		$status_args->application_srl = $application_srl;
		$status_args->status = 'under_review';
		$status_args->last_update = date('YmdHis');
		executeQuery('poomahhi.updateApplicationStatusLite', $status_args);

		$product = $oModel->getProduct($application->product_srl);
		if($product && (int)$product->member_srl !== (int)$logged_info->member_srl)
		{
			$oMemberModel = getModel('member');
			$applicant_member = $oMemberModel->getMemberInfoByMemberSrl($logged_info->member_srl);
			$applicant_nick = $applicant_member && $applicant_member->nick_name ? $applicant_member->nick_name : '회원';
			$applicant_user_id = $applicant_member && !empty($applicant_member->user_id) ? (string)$applicant_member->user_id : '';
			$manage_url = getNotEncodedUrl('', 'mid', $this->mid, 'act', 'dispPoomahhiApplicationManage', 'product_srl', $product->product_srl);
			$cfg = $oModel->getModuleConfig();
			$tpl = isset($cfg->noti_tpl_review_submitted) ? trim((string)$cfg->noti_tpl_review_submitted) : '';
			if($tpl !== '')
			{
				$noti_msg = $oModel->replacePoomahhiNotificationTemplate($tpl, array(
					'product_title' => (string)$product->title,
					'applicant_nick' => (string)$applicant_nick,
					'applicant_user_id' => $applicant_user_id,
				));
			}
			else
			{
				$noti_msg = sprintf('[%s] %s님의 참여 인증이 수정·재제출되었습니다.', $product->title, $applicant_nick);
			}
			// ncenter: 수신=개설자, 발신=신청자(수정요청 후 재제출)
			$this->_sendNotification($logged_info->member_srl, $product->member_srl, $noti_msg, $manage_url, $application_srl);
		}

		$this->setMessage('수정되었습니다.');
		$returnUrl = getNotEncodedUrl('', 'mid', $this->mid, 'act', 'dispPoomahhiApplicationList');
		$this->setRedirectUrl($returnUrl);
	}

	/**
	 * @brief 참여 인증 임시저장
	 */
	function procPoomahhiSaveReviewDraft()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return new BaseObject(-1, '로그인이 필요합니다.');

		$application_srl = (int)Context::get('application_srl');
		if(!$application_srl) return new BaseObject(-1, '신청 정보를 찾을 수 없습니다.');

		$oModel = getModel('poomahhi');
		$application = $oModel->getApplication($application_srl);
		if(!$application) return new BaseObject(-1, '신청 정보를 찾을 수 없습니다.');

		if($application->member_srl != $logged_info->member_srl)
			return new BaseObject(-1, '권한이 없습니다.');

		if(!in_array($application->status, array('selected', 'under_review', 'revision_requested')))
			return new BaseObject(-1, '리뷰를 등록할 수 없는 상태입니다.');

		$existing_review = $oModel->getReviewByApplication($application_srl);
		if($existing_review) return new BaseObject(-1, '이미 리뷰를 등록했습니다.');

		$score = (int)Context::get('score');
		if($score < 1 || $score > 5) $score = 5;
		$content = trim((string)Context::get('content'));

		$new_paths_by_slot = $this->_saveReviewDraftAttachments($application_srl);
		$draft = $oModel->getReviewDraftByApplication($application_srl);

		if($draft)
		{
			$merged = $this->_mergeReviewDraftAttachmentPaths($application_srl, $new_paths_by_slot, $draft);
			$this->_deleteRemovedReviewDraftFiles($draft, $merged);
			$up = new stdClass();
			$up->application_srl = $application_srl;
			$up->score = $score;
			$up->content = $content;
			$up->last_update = date('YmdHis');
			$up->certification_attachment_paths = $merged['certification'];
			$up->purchase_attachment_paths = $merged['purchase'];
			$output = executeQuery('poomahhi.updateReviewDraft', $up);
			if(!$output->toBool()) return $output;
		}
		else
		{
			$cert_paths = array();
			$purchase_paths = array();
			for($i = 0; $i < 6; $i++)
			{
				if(isset($new_paths_by_slot['certification'][$i])) $cert_paths[] = $new_paths_by_slot['certification'][$i];
				if(isset($new_paths_by_slot['purchase'][$i])) $purchase_paths[] = $new_paths_by_slot['purchase'][$i];
			}
			$args = new stdClass();
			$args->draft_srl = getNextSequence();
			$args->application_srl = $application_srl;
			$args->member_srl = $logged_info->member_srl;
			$args->score = $score;
			$args->content = $content;
			$args->certification_attachment_paths = implode(',', $cert_paths);
			$args->purchase_attachment_paths = implode(',', $purchase_paths);
			$output = executeQuery('poomahhi.insertReviewDraft', $args);
			if(!$output->toBool()) return $output;
		}

		$this->setMessage('임시저장되었습니다.');
		$returnUrl = getNotEncodedUrl('', 'mid', Context::get('mid'), 'act', 'dispPoomahhiReviewWrite', 'application_srl', $application_srl);
		$this->setRedirectUrl($returnUrl);
	}

	/**
	 * @brief 받은 리뷰에 답변 등록
	 */
	function procPoomahhiInsertReviewReply()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return new BaseObject(-1, '로그인이 필요합니다.');

		$review_srl = (int)Context::get('review_srl');
		if(!$review_srl) return new BaseObject(-1, '잘못된 요청입니다.');

		$content = trim(Context::get('content'));
		if(!$content) return new BaseObject(-1, '잘못된 요청입니다.');

		$oModel = getModel('poomahhi');
		$review = $oModel->getReview($review_srl);
		if(!$review) return new BaseObject(-1, '잘못된 요청입니다.');

		if($review->member_srl != $logged_info->member_srl)
		{
			return new BaseObject(-1, '권한이 없습니다.');
		}

		$args = new stdClass();
		$args->reply_srl = getNextSequence();
		$args->review_srl = $review_srl;
		$args->member_srl = $logged_info->member_srl;
		$args->content = $content;

		$output = executeQuery('poomahhi.insertReviewReply', $args);
		if(!$output->toBool()) return $output;

		$this->setMessage('등록되었습니다.');
		$returnUrl = getNotEncodedUrl('', 'mid', $this->mid, 'act', 'dispPoomahhiMyReviews');
		$this->setRedirectUrl($returnUrl);
	}

	/**
	 * @brief 등록자가 신청자에 대한 회원 평가 작성
	 */
	function procPoomahhiInsertMemberReview()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return new BaseObject(-1, '로그인이 필요합니다.');

		$application_srl = (int)Context::get('application_srl');
		if(!$application_srl) return new BaseObject(-1, '잘못된 요청입니다.');

		$oModel = getModel('poomahhi');
		$application = $oModel->getApplication($application_srl);
		if(!$application) return new BaseObject(-1, '신청 정보를 찾을 수 없습니다.');

		$product = $oModel->getProduct($application->product_srl);
		if(!$product) return new BaseObject(-1, '상품을 찾을 수 없습니다.');

		if($product->member_srl != $logged_info->member_srl && !$this->_isAdmin($logged_info))
		{
			return new BaseObject(-1, '권한이 없습니다.');
		}

		if(!in_array($application->status, array('under_review', 'completed')))
		{
			return new BaseObject(-1, '회원 평가를 작성할 수 없는 상태입니다.');
		}

		$existing = $oModel->getMemberReviewByApplication($application_srl);
		if($existing) return new BaseObject(-1, '이미 평가를 작성했습니다.');

		$score = (int)Context::get('score');
		if($score < 1 || $score > 5) $score = 5;

		$content = trim(Context::get('content'));

		$args = new stdClass();
		$args->review_srl = getNextSequence();
		$args->application_srl = $application_srl;
		$args->product_srl = $application->product_srl;
		$args->reviewer_member_srl = $logged_info->member_srl;
		$args->target_member_srl = $application->member_srl;
		$args->score = $score;
		$args->content = $content;
		$args->regdate = date('YmdHis');
		$args->last_update = date('YmdHis');

		$output = executeQuery('poomahhi.insertMemberReview', $args);
		if(!$output->toBool()) return $output;

		$status_args = new stdClass();
		$status_args->application_srl = $application_srl;
		$status_args->status = 'completed';
		$status_args->last_update = date('YmdHis');
		executeQuery('poomahhi.updateApplicationStatusLite', $status_args);

		$this->setMessage('등록되었습니다.');
		$returnUrl = Context::get('success_return_url');
		if(!$returnUrl)
		{
			$returnUrl = getNotEncodedUrl('', 'mid', $this->mid, 'act', 'dispPoomahhiApplicationManageDetail', 'application_srl', $application_srl);
		}
		$this->setRedirectUrl($returnUrl);
	}

	/**
	 * @brief 등록자 회원 평가 수정
	 */
	function procPoomahhiUpdateMemberReview()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return new BaseObject(-1, '로그인이 필요합니다.');

		$review_srl = (int)Context::get('review_srl');
		if(!$review_srl) return new BaseObject(-1, '잘못된 요청입니다.');

		$oModel = getModel('poomahhi');
		$mr = $oModel->getMemberReview($review_srl);
		if(!$mr) return new BaseObject(-1, '평가를 찾을 수 없습니다.');

		if($mr->reviewer_member_srl != $logged_info->member_srl && !$this->_isAdmin($logged_info))
		{
			return new BaseObject(-1, '권한이 없습니다.');
		}

		$content = trim((string)Context::get('content'));

		$args = new stdClass();
		$args->review_srl = $review_srl;
		$args->content = $content;
		$args->last_update = date('YmdHis');

		$output = executeQuery('poomahhi.updateMemberReview', $args);
		if(!$output->toBool()) return $output;

		$this->setMessage('수정되었습니다.');
		$returnUrl = Context::get('success_return_url');
		if(!$returnUrl) $returnUrl = getNotEncodedUrl('', 'mid', $this->mid, 'act', 'dispPoomahhiMyReviews');
		$this->setRedirectUrl($returnUrl);
	}

	/**
	 * @brief 관심 품앗이 토글 (추가/제거)
	 */
	function procPoomahhiToggleWishlist()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return new BaseObject(-1, '로그인이 필요합니다.');

		$product_srl = Context::get('product_srl');
		if(!$product_srl) return new BaseObject(-1, '상품을 찾을 수 없습니다.');

		$oModel = getModel('poomahhi');
		$existing = $oModel->getWishlistItem($logged_info->member_srl, $product_srl);

		if($existing)
		{
			// 제거
			$args = new stdClass();
			$args->member_srl = $logged_info->member_srl;
			$args->product_srl = $product_srl;
			$output = executeQuery('poomahhi.deleteWishlist', $args);
			if(!$output->toBool()) return $output;
			$this->add('action', 'removed');
		}
		else
		{
			// 추가
			$args = new stdClass();
			$args->wishlist_srl = getNextSequence();
			$args->member_srl = $logged_info->member_srl;
			$args->product_srl = $product_srl;
			$output = executeQuery('poomahhi.insertWishlist', $args);
			if(!$output->toBool()) return $output;
			$this->add('action', 'added');
		}

		$this->setMessage('수정되었습니다.');

		$returnUrl = Context::get('success_return_url');
		if($returnUrl) $this->setRedirectUrl($returnUrl);
	}

	/**
	 * @brief AJAX: 유료 콘텐츠 열람을 위한 포인트 차감
	 */
	function procPoomahhiDeductPointForView()
	{
		$logged_info = Context::get('logged_info');
		if(!$logged_info) return new BaseObject(-1, '로그인이 필요합니다.');

		$product_srl = (int)Context::get('product_srl');
		if(!$product_srl) return new BaseObject(-1, '상품을 찾을 수 없습니다.');

		$oModel = getModel('poomahhi');
		$product = $oModel->getProduct($product_srl);
		if(!$product) return new BaseObject(-1, '상품을 찾을 수 없습니다.');

		if(($product->content_access_type ?: 'public') !== 'paid')
		{
			return new BaseObject(-1, '유료 콘텐츠가 아닙니다.');
		}

		$point_cost = (int)($product->point_cost ?: 0);
		if($point_cost <= 0) return new BaseObject(-1, '포인트 설정이 올바르지 않습니다.');

		$view_access = $oModel->getProductViewAccess($product_srl, $logged_info->member_srl);
		if($view_access) return new BaseObject(-1, '이미 열람 권한이 있습니다.');

		// 유료 열람 포인트는 라이믹스 포인트 모듈만 사용 (품앗이 자체 포인트 분기는 비활성·레거시 보존)
		if(!class_exists('PointModel'))
		{
			return new BaseObject(-1, '라이믹스 포인트 모듈을 사용할 수 없습니다.');
		}
		$current_point = (int)PointModel::getPoint($logged_info->member_srl);
		if($current_point < $point_cost)
		{
			return new BaseObject(-1, '포인트가 부족합니다. (보유: ' . $current_point . 'P, 필요: ' . $point_cost . 'P)');
		}
		$oPointController = getController('point');
		if(!$oPointController)
		{
			return new BaseObject(-1, '라이믹스 포인트 모듈을 사용할 수 없습니다.');
		}
		$product_title = isset($product->title) ? trim((string)$product->title) : '';
		$msg = $product_title !== ''
			? sprintf('품앗이 유료 콘텐츠 열람: %s (상품 #%d)', cut_str($product_title, 80), $product_srl)
			: sprintf('품앗이 유료 콘텐츠 열람 (상품 #%d)', $product_srl);
		Context::set('__point_message__', $msg);
		$output = $oPointController->setPoint($logged_info->member_srl, $point_cost, 'minus');
		if(!$output || !$output->toBool())
		{
			return new BaseObject(-1, '포인트 차감에 실패했습니다.');
		}

		/*
		[레거시·비활성] 품앗이 자체 포인트(poomahhi_member_point / poomahhi_point_log) 차감 분기 — 추후 확장용으로 보존
		else
		{
			$summary = $oModel->getMyPointSummary($logged_info->member_srl);
			$current_point = (int)$summary->point;
			if($current_point < $point_cost)
			{
				return new BaseObject(-1, '포인트가 부족합니다. (보유: ' . $current_point . 'P, 필요: ' . $point_cost . 'P)');
			}
			$new_point = $current_point - $point_cost;
			$args = new stdClass();
			$args->member_srl = $logged_info->member_srl;
			$args->point = $new_point;
			$args->accumulated_point = $summary->accumulated_point;
			$args->pending_point = $summary->pending_point;
			$args->last_update = date('YmdHis');
			$exists = executeQuery('poomahhi.getMemberPoint', (object)array('member_srl' => $logged_info->member_srl));
			if($exists->toBool() && $exists->data)
			{
				$output = executeQuery('poomahhi.updateMemberPoint', $args);
			}
			else
			{
				$args->accumulated_point = 0;
				$args->pending_point = 0;
				$output = executeQuery('poomahhi.insertMemberPoint', $args);
			}
			if(!$output->toBool()) return new BaseObject(-1, '포인트 차감에 실패했습니다.');

			$log_args = new stdClass();
			$log_args->log_srl = getNextSequence();
			$log_args->member_srl = $logged_info->member_srl;
			$log_args->point = $point_cost;
			$log_args->type = 'deduct';
			$log_args->description = '유료 콘텐츠 열람 (상품 #' . $product_srl . ')';
			$log_args->related_srl = $product_srl;
			$log_args->regdate = date('YmdHis');
			executeQuery('poomahhi.insertPointLog', $log_args);
		}
		*/

		$access_args = new stdClass();
		$access_args->access_srl = getNextSequence();
		$access_args->product_srl = $product_srl;
		$access_args->member_srl = $logged_info->member_srl;
		$access_args->regdate = date('YmdHis');
		$output = executeQuery('poomahhi.insertProductViewAccess', $access_args);
		if(!$output->toBool()) return new BaseObject(-1, '열람 권한 등록에 실패했습니다.');

		$this->setMessage('열람 권한이 부여되었습니다.');
	}

	/**
	 * @brief AJAX: 템플릿별 확장변수 정의 목록 반환
	 */
	function procPoomahhiGetExtraDefsByTemplate()
	{
		$template_srl = Context::get('template_srl');
		if(!$template_srl) return new BaseObject(-1, '잘못된 요청입니다.');

		$args = new stdClass();
		$args->template_srl = $template_srl;
		$output = executeQueryArray('poomahhi.getExtraDefList', $args);

		$extra_defs = ($output->toBool() && $output->data) ? $output->data : array();
		$this->add('extra_defs', $extra_defs);
	}

	/**
	 * @brief AJAX: 지역 품앗이 위젯 내용만 반환 (지역 탭 클릭 시 새로고침 없이 갱신용)
	 */
	function procPoomahhiGetRegionWidgetContent()
	{
		$region_srl = (int) Context::get('region_srl');
		$list_count = (int) Context::get('list_count');
		if ($list_count < 1) $list_count = 6;
		$widget_mid = trim((string) Context::get('widget_mid'));
		if ($widget_mid === '') $widget_mid = null;

		$oModuleModel = getModel('module');
		$module_srl = 0;
		if ($widget_mid)
		{
			$mod = $oModuleModel->getModuleInfoByMid($widget_mid);
			if ($mod && isset($mod->module_srl) && $mod->module === 'poomahhi')
				$module_srl = (int) $mod->module_srl;
		}
		if ($module_srl < 1)
		{
			$mid_list = $oModuleModel->getMidList(null, array('module_srl', 'mid', 'module'));
			foreach ($mid_list as $m)
			{
				if (isset($m->module) && $m->module === 'poomahhi')
				{
					$module_srl = (int) $m->module_srl;
					if (!$widget_mid) $widget_mid = $m->mid;
					break;
				}
			}
		}
		$mod = $module_srl > 0 ? $oModuleModel->getModuleInfoByModuleSrl($module_srl) : null;
		$poomahhi_mid = $mod && isset($mod->mid) ? $mod->mid : '';

		$product_list = array();
		$region_map = array();
		$wishlist_map = array();
		$logged_info = Context::get('logged_info');
		$today = new DateTime('today');

		if ($module_srl > 0 && $region_srl > 0)
		{
			$oModel = getModel('poomahhi');
			$region_title_client = trim(strip_tags((string) Context::get('region_title')));
			if ($region_title_client !== '' && mb_strlen($region_title_client) > 120)
			{
				$region_title_client = mb_substr($region_title_client, 0, 120);
			}
			if ($region_title_client === '')
			{
				$region_list = $oModel->getRegionList($module_srl);
				if ($region_list)
				{
					foreach ($region_list as $rg)
						$region_map[$rg->region_srl] = $rg->title;
				}
			}
			$product_args = new stdClass();
			$product_args->module_srl = $module_srl;
			$product_args->product_type = 'local';
			$product_args->region_srl = $region_srl;
			$product_args->status = 'active';
			$product_args->list_count = $list_count;
			$product_args->page = 1;
			$product_args->page_count = 1;
			$product_output = $oModel->getProductListByWishlistCount($product_args);
			if ($product_output->toBool() && !empty($product_output->data))
				$product_list = array_values($product_output->data);

			if ($product_list && $logged_info)
			{
				$product_srls = array();
				foreach ($product_list as $product)
				{
					$product_srls[] = (int) $product->product_srl;
				}
				$wishlist_map = $oModel->getWishlistMapForMemberProducts($logged_info->member_srl, $product_srls);
			}
			foreach ($product_list as &$product)
			{
				$dday_source = $product->apply_end_date ?: $product->deadline_date;
				if ($dday_source)
				{
					$deadline = DateTime::createFromFormat('YmdHis', $dday_source);
					if ($deadline)
					{
						$diff = $today->diff($deadline);
						$days = (int) $diff->format('%r%a');
						$product->dday = $days;
						$product->dday_text = ($days > 0) ? 'D-' . $days : (($days == 0) ? 'D-Day' : '마감');
					}
				}
				if ($product->short_description)
					$product->content_summary = $product->short_description;
				elseif (!empty($product->content))
					$product->content_summary = mb_strimwidth(strip_tags($product->content), 0, 80, '...');
				if ($region_title_client !== '')
					$product->region_title = $region_title_client;
				elseif ($product->region_srl && isset($region_map[$product->region_srl]))
					$product->region_title = $region_map[$product->region_srl];
				$product->is_paid = (($product->content_access_type ?: 'public') === 'paid' && (int)($product->point_cost ?: 0) > 0);
				$product->point_cost_display = (int)($product->point_cost ?: 0);
			}
		}

		Context::set('product_list', $product_list);
		Context::set('wishlist_map', $wishlist_map);
		Context::set('poomahhi_mid', $poomahhi_mid);
		Context::set('logged_info', $logged_info);

		$skin_path = $this->module_path . 'skins/default/';
		$oTemplate = new Rhymix\Framework\Template($skin_path, 'region_widget_content');
		$html = $oTemplate->compile();
		$this->add('html', $html);
	}

	/**
	 * @brief AJAX: 인기 순위 탭 위젯 내용만 반환 (탭 클릭 시 새로고침 없이 갱신)
	 */
	function procPoomahhiGetPopularDocsTabContent()
	{
		$tab = (int) Context::get('tab');
		if ($tab < 1 || $tab > 3) $tab = 1;
		$list_count = (int) Context::get('list_count');
		if ($list_count < 1) $list_count = 10;

		$module_srl = (int) Context::get('board_' . $tab . '_module_srl');

		$active_list = array();
		if ($module_srl > 0)
		{
			$oModel = getModel('poomahhi');
			$query_args = new stdClass();
			$query_args->module_srl = $module_srl;
			$query_args->status = 'PUBLIC';
			$query_args->list_count = $list_count;
			$query_args->page = 1;
			$query_args->page_count = 1;
			$output = $oModel->getPopularDocumentsByBoards($query_args);
			if ($output->toBool() && !empty($output->data))
			{
				$active_list = array_map(function($row) { return (object)(array)$row; }, array_values($output->data));
			}
		}

		Context::set('active_list', $active_list);

		$widget_name = preg_replace('/[^a-z0-9_]/i', '', (string) Context::get('pdt_widget'));
		$widget_skin = preg_replace('/[^a-z0-9_]/i', '', (string) Context::get('pdt_skin'));
		if ($widget_name === '') $widget_name = 'popular_docs_tabs';
		if ($widget_skin === '') $widget_skin = 'default';
		$skin_path = rtrim(\RX_BASEDIR, '/') . '/widgets/' . $widget_name . '/skins/' . $widget_skin . '/';
		$oTemplate = new Rhymix\Framework\Template($skin_path, 'popular_docs_tab_content');
		$html = $oTemplate->compile();
		$this->add('html', $html);
	}

	/**
	 * @brief 상품 확장변수 저장 헬퍼
	 */
	function _saveProductExtras($product_srl, $template_srl = null)
	{
		// 기존 확장변수 삭제
		executeQuery('poomahhi.deleteProductExtras', (object)array('product_srl' => $product_srl));

		// 확장변수 정의 목록 가져오기 (템플릿 기준)
		$oModel = getModel('poomahhi');
		$args = new stdClass();
		if($template_srl) $args->template_srl = $template_srl;
		$extra_defs_output = executeQueryArray('poomahhi.getExtraDefList', $args);
		$extra_defs = ($extra_defs_output->toBool() && $extra_defs_output->data) ? $extra_defs_output->data : array();
		if(!$extra_defs) return;

		foreach($extra_defs as $def)
		{
			$value = Context::get('extra_' . $def->var_name);
			if($value === null || $value === '') continue;

			$args = new stdClass();
			$args->extra_srl = getNextSequence();
			$args->product_srl = $product_srl;
			$args->var_name = $def->var_name;
			$args->var_value = $value;
			executeQuery('poomahhi.insertProductExtra', $args);
		}
	}

	/**
	 * @brief 대표이미지 업로드 헬퍼
	 * @param int $product_srl
	 * @return string|null 저장된 이미지 경로 또는 null
	 */
	function _uploadProductImage($product_srl)
	{
		if(!isset($_FILES['product_image']) || !$_FILES['product_image']['tmp_name']) return null;

		$file = $_FILES['product_image'];

		// 이미지 파일 검증
		$allowed_ext = array('jpg', 'jpeg', 'png', 'gif', 'webp');
		$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
		if(!in_array($ext, $allowed_ext)) return null;

		// 저장 디렉토리
		$upload_dir = \RX_BASEDIR . 'files/attach/poomahhi/product_images/';
		\FileHandler::makeDir($upload_dir);

		// 파일명: product_srl + 타임스탬프
		$filename = $product_srl . '_' . time() . '.' . $ext;
		$target_path = $upload_dir . $filename;

		if(move_uploaded_file($file['tmp_name'], $target_path))
		{
			return 'files/attach/poomahhi/product_images/' . $filename;
		}
		return null;
	}

	/**
	 * @brief 대표이미지 파일 삭제 헬퍼
	 * @param string $image_path
	 */
	function _deleteProductImage($image_path)
	{
		if(!$image_path) return;
		$full_path = \RX_BASEDIR . $image_path;
		if(file_exists($full_path))
		{
			\FileHandler::removeFile($full_path);
		}
	}

	/**
	 * @brief 날짜 필드 일괄 변환 (Y-m-d -> char(14))
	 */
	function _convertDateFields(&$args)
	{
		$date_fields = array('deadline_date', 'apply_start_date', 'apply_end_date', 'selection_date', 'review_start_date', 'review_end_date', 'result_date');
		foreach($date_fields as $field)
		{
			if(isset($args->{$field}) && $args->{$field} && strlen($args->{$field}) == 10)
			{
				$args->{$field} = str_replace('-', '', $args->{$field}) . '235959';
			}
		}
	}

	/**
	 * @brief 트리거: 로그인 시 회원 유형 일치 여부 확인
	 * member.doLogin (before) 트리거로 호출
	 * 로그인 폼에서 선택한 회원 유형과 실제 회원 유형이 다르면 로그인 차단
	 */
	function triggerBeforeDoLogin(&$obj)
	{
		$login_member_type = Context::get('member_type');
		// 회원 유형 미지정 시 통과 (기본 로그인 폼 등에서 member_type 없이 접근하는 경우)
		if(!$login_member_type) return;

		$user_id = $obj->user_id;
		if(!$user_id) return;

		// 회원 조회
		$oMemberModel = getModel('member');
		$member_info = null;

		if(strpos($user_id, '@') !== false)
		{
			$member_info = $oMemberModel->getMemberInfoByEmailAddress($user_id);
		}
		else
		{
			$member_info = $oMemberModel->getMemberInfoByUserID($user_id);
		}

		if(!$member_info || !$member_info->member_srl) return;

		// 회원의 실제 member_type 확인 (미설정 시 general로 간주)
		$actual_type = $member_info->member_type ?: 'general';

		if($login_member_type !== $actual_type)
		{
			return new BaseObject(-1, '회원 유형이 일치하지 않습니다. 올바른 탭에서 로그인해주세요.');
		}
	}

	/**
	 * @brief 비즈니스 회원 여부 확인
	 */
	function _isBusinessMember($logged_info)
	{
		if(!$logged_info) return false;

		// 회원 확장변수에서 member_type 확인
		// Rhymix의 arrangeMemberInfo()가 extra_vars를 직접 속성으로 풀어놓으므로 member_type으로 접근
		$oMemberModel = getModel('member');
		$member_info = $oMemberModel->getMemberInfoByMemberSrl($logged_info->member_srl);
		if($member_info && $member_info->member_type === 'business')
		{
			return true;
		}
		return false;
	}

	/**
	 * @brief 관리자 여부 확인
	 */
	function _isAdmin($logged_info)
	{
		if(!$logged_info) return false;
		return ($logged_info->is_admin === 'Y');
	}

	/**
	 * @brief 참여인증 폼에서 업로드된 이미지를 디스크에 저장하고 경로 목록 반환
	 * 리뷰작성 인증샷·구매경로 인증샷 각각 최대 6장까지 저장
	 * @param int $review_srl
	 * @return string 쉼표로 구분된 상대 경로 (files/attach/poomahhi/review/...)
	 */
	function _saveReviewAttachments($review_srl)
	{
		if(!$review_srl) return '';

		$max_per_category = 6;
		$allowed_ext = array('jpg', 'jpeg', 'png', 'gif', 'webp');
		$upload_dir = \RX_BASEDIR . 'files/attach/poomahhi/review/' . $review_srl . '/';
		\FileHandler::makeDir($upload_dir);

		$paths = array();

		$categories = array(
			array('key' => 'certification_images', 'max' => $max_per_category),
			array('key' => 'purchase_path_images', 'max' => $max_per_category),
		);

		foreach($categories as $cat)
		{
			if(!isset($_FILES[$cat['key']]) || !is_array($_FILES[$cat['key']])) continue;

			$files = $_FILES[$cat['key']];
			$list = array();
			if(isset($files['name']) && is_array($files['name']))
			{
				foreach($files['name'] as $i => $name)
				{
					if((int)$files['error'][$i] !== UPLOAD_ERR_OK || !$files['tmp_name'][$i]) continue;
					$list[] = array('name' => $files['name'][$i], 'tmp_name' => $files['tmp_name'][$i]);
				}
			}
			elseif(!empty($files['tmp_name']) && (int)$files['error'] === UPLOAD_ERR_OK)
			{
				$list[] = array('name' => $files['name'], 'tmp_name' => $files['tmp_name']);
			}

			$count = 0;
			foreach($list as $f)
			{
				if($count >= $cat['max']) break;
				$ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
				if(!in_array($ext, $allowed_ext)) continue;
				$filename = uniqid('', true) . '.' . $ext;
				$target = $upload_dir . $filename;
				if(move_uploaded_file($f['tmp_name'], $target))
				{
					$paths[] = 'files/attach/poomahhi/review/' . $review_srl . '/' . $filename;
					$count++;
				}
			}
		}

		return $paths ? implode(',', $paths) : '';
	}

	/**
	 * 최종 제출 시 참여인증 이미지를 review 폴더에 슬롯별 저장
	 * @param int $review_srl
	 * @return array ['certification' => [인덱스=>경로], 'purchase' => [...]]
	 */
	function _saveReviewAttachmentsForReview($review_srl)
	{
		$result = array('certification' => array(), 'purchase' => array());
		if(!$review_srl) return $result;

		$allowed_ext = array('jpg', 'jpeg', 'png', 'gif', 'webp');
		$upload_dir = \RX_BASEDIR . 'files/attach/poomahhi/review/' . $review_srl . '/';
		\FileHandler::makeDir($upload_dir);

		$categories = array(
			array('key' => 'certification_images', 'out' => 'certification'),
			array('key' => 'purchase_path_images', 'out' => 'purchase'),
		);

		foreach($categories as $cat)
		{
			if(!isset($_FILES[$cat['key']]) || !is_array($_FILES[$cat['key']])) continue;
			$files = $_FILES[$cat['key']];
			if(!isset($files['name']) || !is_array($files['name'])) continue;
			foreach($files['name'] as $i => $name)
			{
				if(!is_string($name) || $name === '') continue;
				if((int)(isset($files['error'][$i]) ? $files['error'][$i] : UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
				if(empty($files['tmp_name'][$i])) continue;
				$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
				if(!in_array($ext, $allowed_ext)) continue;
				$filename = uniqid('', true) . '.' . $ext;
				$target = $upload_dir . $filename;
				if(move_uploaded_file($files['tmp_name'][$i], $target))
				{
					$result[$cat['out']][(int)$i] = 'files/attach/poomahhi/review/' . $review_srl . '/' . $filename;
				}
			}
		}
		return $result;
	}

	/**
	 * 새 업로드 + draft 이미지(복사)를 합쳐 리뷰용 certification / purchase 경로 문자열 각각 반환 (draft와 동일 구조)
	 */
	function _buildReviewAttachmentPathsFromDraftAndNew($review_srl, $application_srl, $new_paths_by_slot, $draft)
	{
		$base = rtrim(\RX_BASEDIR, '/') . '/';
		$review_dir = $base . 'files/attach/poomahhi/review/' . $review_srl . '/';
		\FileHandler::makeDir($review_dir);

		$draft_cert = array();
		$draft_purchase = array();
		if($draft)
		{
			if(!empty($draft->certification_attachment_paths))
				$draft_cert = array_map('trim', explode(',', $draft->certification_attachment_paths));
			if(!empty($draft->purchase_attachment_paths))
				$draft_purchase = array_map('trim', explode(',', $draft->purchase_attachment_paths));
		}

		$cert_paths = array();
		for($i = 0; $i < 6; $i++)
		{
			if(isset($new_paths_by_slot['certification'][$i]))
				$cert_paths[] = $new_paths_by_slot['certification'][$i];
			elseif(isset($draft_cert[$i]) && $draft_cert[$i] !== '')
			{
				$src = $base . ltrim($draft_cert[$i], '/');
				if(is_file($src))
				{
					$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
					$dest_name = uniqid('', true) . '.' . $ext;
					$dest = $review_dir . $dest_name;
					if(@copy($src, $dest))
						$cert_paths[] = 'files/attach/poomahhi/review/' . $review_srl . '/' . $dest_name;
				}
			}
		}
		$purchase_paths = array();
		for($i = 0; $i < 6; $i++)
		{
			if(isset($new_paths_by_slot['purchase'][$i]))
				$purchase_paths[] = $new_paths_by_slot['purchase'][$i];
			elseif(isset($draft_purchase[$i]) && $draft_purchase[$i] !== '')
			{
				$src = $base . ltrim($draft_purchase[$i], '/');
				if(is_file($src))
				{
					$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
					$dest_name = uniqid('', true) . '.' . $ext;
					$dest = $review_dir . $dest_name;
					if(@copy($src, $dest))
						$purchase_paths[] = 'files/attach/poomahhi/review/' . $review_srl . '/' . $dest_name;
				}
			}
		}
		return array(
			'certification' => $cert_paths ? implode(',', $cert_paths) : '',
			'purchase' => $purchase_paths ? implode(',', $purchase_paths) : '',
		);
	}

	/**
	 * @brief 참여 인증 임시저장용 이미지 저장 (review_draft 폴더)
	 * @param int $application_srl
	 * @return string 쉼표 구분 경로
	 */
	/**
	 * 슬롯별(0~5) 새 업로드만 처리. 반환: ['certification' => [인덱스=>경로, ...], 'purchase' => [...]]
	 */
	function _saveReviewDraftAttachments($application_srl)
	{
		$result = array('certification' => array(), 'purchase' => array());
		if(!$application_srl) return $result;

		$allowed_ext = array('jpg', 'jpeg', 'png', 'gif', 'webp');
		$upload_dir = \RX_BASEDIR . 'files/attach/poomahhi/review_draft/' . $application_srl . '/';
		\FileHandler::makeDir($upload_dir);

		$categories = array(
			array('key' => 'certification_images', 'out' => 'certification'),
			array('key' => 'purchase_path_images', 'out' => 'purchase'),
		);

		foreach($categories as $cat)
		{
			if(!isset($_FILES[$cat['key']]) || !is_array($_FILES[$cat['key']])) continue;
			$files = $_FILES[$cat['key']];
			if(!isset($files['name']) || !is_array($files['name'])) continue;
			foreach($files['name'] as $i => $name)
			{
				if(!is_string($name) || $name === '') continue;
				if((int)(isset($files['error'][$i]) ? $files['error'][$i] : UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
				if(empty($files['tmp_name'][$i])) continue;
				$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
				if(!in_array($ext, $allowed_ext)) continue;
				$filename = uniqid('', true) . '.' . $ext;
				$target = $upload_dir . $filename;
				if(move_uploaded_file($files['tmp_name'][$i], $target))
				{
					$result[$cat['out']][(int)$i] = 'files/attach/poomahhi/review_draft/' . $application_srl . '/' . $filename;
				}
			}
		}
		return $result;
	}

	/**
	 * 슬롯별 새 업로드 + 삭제 플래그 + 기존 draft 경로를 합쳐 최종 certification/purchase 경로 문자열 반환
	 */
	function _mergeReviewDraftAttachmentPaths($application_srl, $new_paths_by_slot, $draft)
	{
		$cert_paths = array();
		$purchase_paths = array();
		$draft_cert = isset($draft->certification_attachment_paths) && $draft->certification_attachment_paths !== ''
			? explode(',', $draft->certification_attachment_paths) : array();
		$draft_purchase = isset($draft->purchase_attachment_paths) && $draft->purchase_attachment_paths !== ''
			? explode(',', $draft->purchase_attachment_paths) : array();
		for($i = 0; $i < 6; $i++)
		{
			$removed = (int)Context::get('certification_removed_' . $i);
			if(isset($new_paths_by_slot['certification'][$i]))
				$cert_paths[] = $new_paths_by_slot['certification'][$i];
			elseif(!$removed && isset($draft_cert[$i]) && trim($draft_cert[$i]) !== '')
				$cert_paths[] = trim($draft_cert[$i]);
		}
		for($i = 0; $i < 6; $i++)
		{
			$removed = (int)Context::get('purchase_removed_' . $i);
			if(isset($new_paths_by_slot['purchase'][$i]))
				$purchase_paths[] = $new_paths_by_slot['purchase'][$i];
			elseif(!$removed && isset($draft_purchase[$i]) && trim($draft_purchase[$i]) !== '')
				$purchase_paths[] = trim($draft_purchase[$i]);
		}
		return array(
			'certification' => implode(',', $cert_paths),
			'purchase' => implode(',', $purchase_paths),
		);
	}

	/**
	 * 기존 리뷰 경로 + 새 업로드 + 삭제 플래그를 합쳐 최종 certification/purchase 경로 문자열 반환 (리뷰 수정용)
	 */
	function _mergeReviewAttachmentPathsForUpdate($new_paths_by_slot, $review)
	{
		$cert_paths = array();
		$purchase_paths = array();
		$review_cert = isset($review->certification_attachment_paths) && $review->certification_attachment_paths !== ''
			? explode(',', $review->certification_attachment_paths) : array();
		$review_purchase = isset($review->purchase_attachment_paths) && $review->purchase_attachment_paths !== ''
			? explode(',', $review->purchase_attachment_paths) : array();
		for($i = 0; $i < 6; $i++)
		{
			$removed = (int)Context::get('certification_removed_' . $i);
			if(isset($new_paths_by_slot['certification'][$i]))
				$cert_paths[] = $new_paths_by_slot['certification'][$i];
			elseif(!$removed && isset($review_cert[$i]) && trim($review_cert[$i]) !== '')
				$cert_paths[] = trim($review_cert[$i]);
		}
		for($i = 0; $i < 6; $i++)
		{
			$removed = (int)Context::get('purchase_removed_' . $i);
			if(isset($new_paths_by_slot['purchase'][$i]))
				$purchase_paths[] = $new_paths_by_slot['purchase'][$i];
			elseif(!$removed && isset($review_purchase[$i]) && trim($review_purchase[$i]) !== '')
				$purchase_paths[] = trim($review_purchase[$i]);
		}
		return array(
			'certification' => implode(',', $cert_paths),
			'purchase' => implode(',', $purchase_paths),
		);
	}

	/**
	 * 리뷰에서 제거된 경로에 해당하는 서버 파일 삭제 (DB에서 빠진 경로만 삭제)
	 */
	function _deleteRemovedReviewFiles($review, $merged)
	{
		$base = rtrim(\RX_BASEDIR, '/') . '/';
		$to_delete = array();
		foreach(array('certification_attachment_paths', 'purchase_attachment_paths') as $key)
		{
			$old_str = isset($review->$key) ? (string)$review->$key : '';
			$new_str = ($key === 'certification_attachment_paths') ? $merged['certification'] : $merged['purchase'];
			$old_paths = $old_str !== '' ? array_map('trim', explode(',', $old_str)) : array();
			$new_paths = $new_str !== '' ? array_map('trim', explode(',', $new_str)) : array();
			foreach($old_paths as $path)
			{
				if($path === '') continue;
				if(!in_array($path, $new_paths, true))
					$to_delete[] = $base . ltrim($path, '/');
			}
		}
		foreach($to_delete as $full_path)
		{
			if(is_file($full_path) && strpos(realpath($full_path), realpath(\RX_BASEDIR)) === 0)
				@unlink($full_path);
		}
	}

	/**
	 * draft에서 제거된 경로에 해당하는 서버 파일 삭제 (DB에서 빠진 경로만 삭제)
	 */
	function _deleteRemovedReviewDraftFiles($draft, $merged)
	{
		$base = rtrim(\RX_BASEDIR, '/') . '/';
		$to_delete = array();
		foreach(array('certification_attachment_paths', 'purchase_attachment_paths') as $key)
		{
			$old_str = isset($draft->$key) ? (string)$draft->$key : '';
			$new_str = ($key === 'certification_attachment_paths') ? $merged['certification'] : $merged['purchase'];
			$old_paths = $old_str !== '' ? array_map('trim', explode(',', $old_str)) : array();
			$new_paths = $new_str !== '' ? array_map('trim', explode(',', $new_str)) : array();
			foreach($old_paths as $path)
			{
				if($path === '') continue;
				if(!in_array($path, $new_paths, true))
					$to_delete[] = $base . ltrim($path, '/');
			}
		}
		foreach($to_delete as $full_path)
		{
			if(is_file($full_path) && strpos(realpath($full_path), realpath(\RX_BASEDIR)) === 0)
				@unlink($full_path);
		}
	}

	/**
	 * 임시저장 폴더 전체 삭제 (최종 제출 시 등)
	 */
	function _deleteReviewDraftFolder($application_srl)
	{
		if(!$application_srl) return;
		$dir = \RX_BASEDIR . 'files/attach/poomahhi/review_draft/' . $application_srl . '/';
		if(!is_dir($dir)) return;
		$base_real = realpath(\RX_BASEDIR);
		if(strpos(realpath($dir), $base_real) !== 0) return;
		foreach((array)@scandir($dir) as $name)
		{
			if($name === '.' || $name === '..') continue;
			$path = $dir . $name;
			if(is_file($path)) @unlink($path);
		}
		@rmdir($dir);
	}

	/**
	 * @brief 에디터 첨부파일 유효화 헬퍼
	 */
	function _updateUploadedFiles($target_srl)
	{
		if(!$target_srl) return;
		$oFileController = getController('file');
		$oFileController->setFilesValid($target_srl);
	}
}
