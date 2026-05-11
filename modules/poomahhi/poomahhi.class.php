<?php
/**
 * @class poomahhi
 * @author WP
 * @brief 품앗이(체험단) 플랫폼 모듈 기본 클래스
 */
class poomahhi extends ModuleObject
{
	/** 트리거 정의: [트리거명, 모듈, 타입, 메서드, 위치] */
	private $triggers = array(
		array('moduleHandler.init', 'poomahhi', 'controller', 'triggerModuleHandlerInitBefore', 'before'),
		array('moduleHandler.init', 'poomahhi', 'controller', 'triggerAddMemberMenu', 'after'),
		array('member.procMemberInsert', 'poomahhi', 'controller', 'triggerAfterMemberInsert', 'after'),
		array('member.dispMemberSignUpForm', 'poomahhi', 'controller', 'triggerBeforeDispMemberSignUpForm', 'before'),
		array('member.doLogin', 'poomahhi', 'controller', 'triggerBeforeDoLogin', 'before'),
		array('document.getDocumentList', 'poomahhi', 'controller', 'triggerDocumentGetDocumentListBefore', 'before'),
		array('document.getDocumentList', 'poomahhi', 'controller', 'triggerDocumentGetDocumentListAfter', 'after'),
	);

	/**
	 * @brief 모듈 최초 설치 시 실행
	 * @return BaseObject
	 */
	function moduleInstall()
	{
		$oModuleController = getController('module');
		$oModuleModel = getModel('module');

		$module_info = $oModuleModel->getModuleInfoByMid('poomahhi');
		if(!$module_info || !$module_info->module_srl)
		{
			$args = new stdClass();
			$args->mid = 'poomahhi';
			$args->module = 'poomahhi';
			$args->browser_title = '품앗이';
			$args->site_srl = 0;
			$args->skin = 'default';
			$oModuleController->insertModule($args);
		}

		// 지역 품앗이 mid 생성
		$local_module_info = $oModuleModel->getModuleInfoByMid('local_poomahhi');
		if(!$local_module_info || !$local_module_info->module_srl)
		{
			$args = new stdClass();
			$args->mid = 'local_poomahhi';
			$args->module = 'poomahhi';
			$args->browser_title = '지역 품앗이';
			$args->site_srl = 0;
			$args->skin = 'default';
			$oModuleController->insertModule($args);
		}

		// 사이트맵 등에서 선택 가능한 개설자 대시보드 전용 mid (기본 act는 트리거에서 지정)
		$biz_module_info = $oModuleModel->getModuleInfoByMid('poomahhi_business');
		if(!$biz_module_info || !$biz_module_info->module_srl)
		{
			$args = new stdClass();
			$args->mid = 'poomahhi_business';
			$args->module = 'poomahhi';
			$args->browser_title = '비즈니스 홈';
			$args->site_srl = 0;
			$args->skin = 'default';
			$oModuleController->insertModule($args);
		}

		foreach($this->triggers as $trigger)
		{
			$oModuleController->insertTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
		}

		return new BaseObject();
	}

	/**
	 * @brief 업데이트 필요 여부 확인
	 * @return bool
	 */
	function checkUpdate()
	{
		$oModuleModel = getModel('module');

		$module_info = $oModuleModel->getModuleInfoByMid('poomahhi');
		if(!$module_info || !$module_info->module_srl)
		{
			return true;
		}

		// module 필드 오염 감지 (module=admin 등으로 잘못 저장된 경우)
		if($module_info->module !== 'poomahhi')
		{
			return true;
		}

		// 지역 품앗이 mid 존재 여부
		$local_module_info = $oModuleModel->getModuleInfoByMid('local_poomahhi');
		if(!$local_module_info || !$local_module_info->module_srl)
		{
			return true;
		}

		// 개설자 대시보드용 mid
		$biz_module_info = $oModuleModel->getModuleInfoByMid('poomahhi_business');
		if(!$biz_module_info || !$biz_module_info->module_srl)
		{
			return true;
		}

		foreach($this->triggers as $trigger)
		{
			if(!$oModuleModel->getTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]))
			{
				return true;
			}
		}

		$oDB = DB::getInstance();
		if(!$oDB->isColumnExists('poomahhi_product', 'product_image')) return true;
		if(!$oDB->isColumnExists('poomahhi_product', 'extra_template_srl')) return true;
		if(!$oDB->isColumnExists('poomahhi_extra_def', 'template_srl')) return true;
		if(!$oDB->isTableExists('poomahhi_extra_template')) return true;

		$new_columns = array('short_description','actual_price','apply_start_date','apply_end_date','selection_date','review_start_date','review_end_date','result_date','provision','mission','mission_tags','keywords','notice');
		foreach($new_columns as $col)
		{
			if(!$oDB->isColumnExists('poomahhi_product', $col)) return true;
		}

		// 지역 품앗이 관련 컬럼/테이블
		if(!$oDB->isTableExists('poomahhi_region')) return true;
		$local_columns = array('product_type','region_srl','contact','zipcode','address','address_detail','visit_info');
		foreach($local_columns as $col)
		{
			if(!$oDB->isColumnExists('poomahhi_product', $col)) return true;
		}

		// poomahhi_application 테이블/컬럼
		if(!$oDB->isTableExists('poomahhi_application')) return true;
		$app_columns = array('applicant_comment', 'applicant_name', 'birth_date', 'gender', 'phone', 'applicant_contact_name', 'applicant_contact_phone', 'applicant_zipcode', 'applicant_address', 'applicant_address_detail', 'privacy_agreed', 'deadline', 'admin_memo', 'rejection_reason', 'rejection_detail', 'revision_request_content', 'regdate', 'last_update');
		foreach($app_columns as $col)
		{
			if(!$oDB->isColumnExists('poomahhi_application', $col)) return true;
		}

		// poomahhi_member_review (회원평가) 테이블
		if(!$oDB->isTableExists('poomahhi_member_review')) return true;

		// poomahhi_member_fulfillment_extra (삭제된 신청의 미이행 누적)
		if(!$oDB->isTableExists('poomahhi_member_fulfillment_extra')) return true;

		// poomahhi_member_point, poomahhi_point_log (내 포인트)
		if(!$oDB->isTableExists('poomahhi_member_point')) return true;
		if(!$oDB->isTableExists('poomahhi_point_log')) return true;

		// poomahhi_product_view_access (유료 콘텐츠 열람 기록)
		if(!$oDB->isTableExists('poomahhi_product_view_access')) return true;

		// content_access_type, point_cost (유료 콘텐츠)
		$content_access_columns = array('content_access_type', 'point_cost');
		foreach($content_access_columns as $col)
		{
			if(!$oDB->isColumnExists('poomahhi_product', $col)) return true;
		}

		// 불필요 트리거 정리 (코어 미지원 트리거 삭제 대상)
		$deprecated_triggers = array(
			array('member.dispMemberInfo', 'poomahhi', 'controller', 'triggerAddMemberMenu', 'before'),
			array('member.doLogin', 'poomahhi', 'controller', 'triggerAfterDoLoginAddMemberMenu', 'after'),
		);
		foreach($deprecated_triggers as $dt)
		{
			if($oModuleModel->getTrigger($dt[0], $dt[1], $dt[2], $dt[3], $dt[4]))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * @brief 업데이트 실행
	 * @return BaseObject
	 */
	function moduleUpdate()
	{
		$oModuleController = getController('module');
		$oModuleModel = getModel('module');

		$module_info = $oModuleModel->getModuleInfoByMid('poomahhi');
		if(!$module_info || !$module_info->module_srl)
		{
			$args = new stdClass();
			$args->mid = 'poomahhi';
			$args->module = 'poomahhi';
			$args->browser_title = '품앗이';
			$args->site_srl = 0;
			$args->skin = 'default';
			$oModuleController->insertModule($args);
		}
		// module 필드 오염 복구 (module=admin 등으로 잘못 저장된 경우)
		elseif($module_info->module !== 'poomahhi')
		{
			$fix_args = new stdClass();
			$fix_args->module_srl = $module_info->module_srl;
			$fix_args->module = 'poomahhi';
			$fix_args->mid = 'poomahhi';
			$fix_args->browser_title = $module_info->browser_title ?: '품앗이';
			$fix_args->skin = $module_info->skin ?: 'default';
			$oModuleController->updateModule($fix_args);
		}

		// 지역 품앗이 mid 생성
		$local_module_info = $oModuleModel->getModuleInfoByMid('local_poomahhi');
		if(!$local_module_info || !$local_module_info->module_srl)
		{
			$args = new stdClass();
			$args->mid = 'local_poomahhi';
			$args->module = 'poomahhi';
			$args->browser_title = '지역 품앗이';
			$args->site_srl = 0;
			$args->skin = 'default';
			$oModuleController->insertModule($args);
		}

		$biz_module_info = $oModuleModel->getModuleInfoByMid('poomahhi_business');
		if(!$biz_module_info || !$biz_module_info->module_srl)
		{
			$args = new stdClass();
			$args->mid = 'poomahhi_business';
			$args->module = 'poomahhi';
			$args->browser_title = '비즈니스 홈';
			$args->site_srl = 0;
			$args->skin = 'default';
			$oModuleController->insertModule($args);
		}

		foreach($this->triggers as $trigger)
		{
			if(!$oModuleModel->getTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]))
			{
				$oModuleController->insertTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
			}
		}

		// 불필요 트리거 삭제 (코어에서 호출하지 않는 트리거 정리)
		$deprecated_triggers = array(
			array('member.dispMemberInfo', 'poomahhi', 'controller', 'triggerAddMemberMenu', 'before'),
			array('member.doLogin', 'poomahhi', 'controller', 'triggerAfterDoLoginAddMemberMenu', 'after'),
		);
		foreach($deprecated_triggers as $dt)
		{
			if($oModuleModel->getTrigger($dt[0], $dt[1], $dt[2], $dt[3], $dt[4]))
			{
				$oModuleController->deleteTrigger($dt[0], $dt[1], $dt[2], $dt[3], $dt[4]);
			}
		}

		// 컬럼 마이그레이션 실행
		$oDB = DB::getInstance();
		if(!$oDB->isColumnExists('poomahhi_product', 'product_image'))
		{
			$oDB->addColumn('poomahhi_product', 'product_image', 'varchar', 500);
		}
		if(!$oDB->isColumnExists('poomahhi_product', 'extra_template_srl'))
		{
			$oDB->addColumn('poomahhi_product', 'extra_template_srl', 'number', 11, 0);
		}
		if(!$oDB->isColumnExists('poomahhi_extra_def', 'template_srl'))
		{
			$oDB->addColumn('poomahhi_extra_def', 'template_srl', 'number', 11, 0);
		}
		if(!$oDB->isTableExists('poomahhi_extra_template'))
		{
			$oDB->createTableByXmlFile($this->module_path . 'schemas/poomahhi_extra_template.xml');
		}

		$column_defs = array(
			'short_description' => array('varchar', 500),
			'actual_price' => array('number', 11, 0),
			'apply_start_date' => array('varchar', 14),
			'apply_end_date' => array('varchar', 14),
			'selection_date' => array('varchar', 14),
			'review_start_date' => array('varchar', 14),
			'review_end_date' => array('varchar', 14),
			'result_date' => array('varchar', 14),
			'provision' => array('text'),
			'mission' => array('text'),
			'mission_tags' => array('varchar', 500),
			'keywords' => array('text'),
			'notice' => array('text'),
		);
		foreach($column_defs as $col_name => $def)
		{
			if(!$oDB->isColumnExists('poomahhi_product', $col_name))
			{
				$type = $def[0];
				$size = isset($def[1]) ? $def[1] : null;
				$default = isset($def[2]) ? $def[2] : null;
				$oDB->addColumn('poomahhi_product', $col_name, $type, $size, $default);
			}
		}

		// 지역 품앗이 테이블 생성
		if(!$oDB->isTableExists('poomahhi_region'))
		{
			$oDB->createTableByXmlFile($this->module_path . 'schemas/poomahhi_region.xml');
		}

		// 지역 품앗이 관련 컬럼 추가
		$local_column_defs = array(
			'product_type' => array('varchar', 20, 'product'),
			'region_srl' => array('number', 11, 0),
			'contact' => array('varchar', 250),
			'zipcode' => array('varchar', 10),
			'address' => array('varchar', 500),
			'address_detail' => array('varchar', 500),
			'visit_info' => array('text'),
		);
		foreach($local_column_defs as $col_name => $def)
		{
			if(!$oDB->isColumnExists('poomahhi_product', $col_name))
			{
				$type = $def[0];
				$size = isset($def[1]) ? $def[1] : null;
				$default = isset($def[2]) ? $def[2] : null;
				$oDB->addColumn('poomahhi_product', $col_name, $type, $size, $default);
			}
		}

		// poomahhi_member_review (회원평가) 테이블 생성
		if(!$oDB->isTableExists('poomahhi_member_review'))
		{
			$oDB->createTableByXmlFile($this->module_path . 'schemas/poomahhi_member_review.xml');
		}

		// poomahhi_member_fulfillment_extra (삭제된 신청의 미이행 누적)
		if(!$oDB->isTableExists('poomahhi_member_fulfillment_extra'))
		{
			$oDB->createTableByXmlFile($this->module_path . 'schemas/poomahhi_member_fulfillment_extra.xml');
		}

		// poomahhi_member_point, poomahhi_point_log (내 포인트)
		if(!$oDB->isTableExists('poomahhi_member_point'))
		{
			$oDB->createTableByXmlFile($this->module_path . 'schemas/poomahhi_member_point.xml');
		}
		if(!$oDB->isTableExists('poomahhi_point_log'))
		{
			$oDB->createTableByXmlFile($this->module_path . 'schemas/poomahhi_point_log.xml');
		}

		// poomahhi_product_view_access (유료 콘텐츠 열람 기록)
		if(!$oDB->isTableExists('poomahhi_product_view_access'))
		{
			$oDB->createTableByXmlFile($this->module_path . 'schemas/poomahhi_product_view_access.xml');
		}

		// content_access_type, point_cost 컬럼 추가
		$content_access_column_defs = array(
			'content_access_type' => array('varchar', 20, 'public'),
			'point_cost' => array('number', 11, 0),
		);
		foreach($content_access_column_defs as $col_name => $def)
		{
			if(!$oDB->isColumnExists('poomahhi_product', $col_name))
			{
				$type = $def[0];
				$size = isset($def[1]) ? $def[1] : null;
				$default = isset($def[2]) ? $def[2] : null;
				$oDB->addColumn('poomahhi_product', $col_name, $type, $size, $default);
			}
		}

		// poomahhi_application 테이블 생성 또는 누락 컬럼 추가
		if(!$oDB->isTableExists('poomahhi_application'))
		{
			$oDB->createTableByXmlFile($this->module_path . 'schemas/poomahhi_application.xml');
		}
		else
		{
			$app_column_defs = array(
				'applicant_comment' => array('text'),
				'applicant_name' => array('varchar', 100),
				'birth_date' => array('varchar', 20),
				'gender' => array('varchar', 10),
				'phone' => array('varchar', 20),
				'applicant_contact_name' => array('varchar', 100),
				'applicant_contact_phone' => array('varchar', 20),
				'applicant_zipcode' => array('varchar', 20),
				'applicant_address' => array('varchar', 500),
				'applicant_address_detail' => array('varchar', 500),
				'privacy_agreed' => array('varchar', 1, 'N'),
				'deadline' => array('varchar', 14),
				'admin_memo' => array('text'),
				'rejection_reason' => array('varchar', 100),
				'rejection_detail' => array('text'),
				'revision_request_content' => array('text'),
				'regdate' => array('varchar', 14),
				'last_update' => array('varchar', 14),
			);
			foreach($app_column_defs as $col_name => $def)
			{
				if(!$oDB->isColumnExists('poomahhi_application', $col_name))
				{
					$type = $def[0];
					$size = isset($def[1]) ? $def[1] : null;
					$default = isset($def[2]) ? $def[2] : null;
					$oDB->addColumn('poomahhi_application', $col_name, $type, $size, $default);
				}
			}
		}

		return new BaseObject();
	}

	/**
	 * @brief 캐시 재생성 (필요 시 구현)
	 */
	function recompileCache()
	{
	}

	/**
	 * @brief 모듈 삭제 시 실행
	 * @return BaseObject
	 */
	function moduleUninstall()
	{
		$oModuleController = getController('module');

		foreach($this->triggers as $trigger)
		{
			$oModuleController->deleteTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
		}

		return new BaseObject();
	}
}
/* End of file poomahhi.class.php */
/* Location: ./modules/poomahhi/poomahhi.class.php */
