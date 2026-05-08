<?php
/**
 * @class poomahhiModel
 * @author WP
 * @brief 품앗이 모듈 프론트 모델
 */
class poomahhiModel extends poomahhi
{
	/**
	 * @brief 초기화
	 */
	function init()
	{
	}

	/**
	 * @brief 상품 단일 조회
	 */
	function getProduct($product_srl)
	{
		$args = new stdClass();
		$args->product_srl = $product_srl;
		$output = executeQuery('poomahhi.getProduct', $args);
		if(!$output->toBool() || !$output->data) return null;
		return $output->data;
	}

	/**
	 * @brief 유료 콘텐츠 열람 권한 여부 (한번 차감 후 영구 열람)
	 */
	function getProductViewAccess($product_srl, $member_srl)
	{
		if(!$product_srl || !$member_srl) return null;
		$args = new stdClass();
		$args->product_srl = $product_srl;
		$args->member_srl = $member_srl;
		$output = executeQuery('poomahhi.getProductViewAccess', $args);
		if(!$output->toBool() || !$output->data) return null;
		return $output->data;
	}

	/**
	 * @brief 상품 목록 조회
	 */
	function getProductList($args)
	{
		$output = executeQueryArray('poomahhi.getProductList', $args);
		return $output;
	}

	/**
	 * @brief 상품 목록 조회 (통합 검색용, module_srl_list 지원)
	 */
	function getProductListSearch($args)
	{
		$output = executeQueryArray('poomahhi.getProductListSearch', $args);
		return $output;
	}

	/**
	 * @brief 상품 목록 조회 (찜 수 많은 순, 위젯용)
	 */
	function getProductListByWishlistCount($args)
	{
		$output = executeQueryArray('poomahhi.getProductListByWishlistCount', $args);
		return $output;
	}

	/**
	 * @brief 게시판 인기 게시물 목록 (추천 수 순, 내돈내산 위젯용)
	 */
	function getPopularDocumentsByBoards($args)
	{
		$output = executeQueryArray('poomahhi.getPopularDocumentsByBoards', $args);
		return $output;
	}

	/**
	 * @brief 상품 확장변수 조회
	 */
	function getProductExtraVars($product_srl)
	{
		$args = new stdClass();
		$args->product_srl = $product_srl;
		$output = executeQueryArray('poomahhi.getProductExtraVars', $args);
		if(!$output->toBool()) return array();
		return $output->data ?: array();
	}

	/**
	 * @brief 신청 단일 조회
	 */
	function getApplication($application_srl)
	{
		$args = new stdClass();
		$args->application_srl = $application_srl;
		$output = executeQuery('poomahhi.getApplication', $args);
		if(!$output->toBool() || !$output->data) return null;
		return $output->data;
	}

	/**
	 * @brief 신청 목록 조회 (관리자: product_srl, status 필터)
	 */
	function getApplicationList($args)
	{
		$output = executeQueryArray('poomahhi.getApplicationList', $args);
		return $output;
	}

	/**
	 * @brief 회원별 신청 목록 조회 (프론트 내 신청 현황: member_srl 필수, status 또는 status_list 선택)
	 * @param object $args member_srl 필수, status(단일) 또는 status_list(콤마구분 문자열, 반려/취소 탭용)
	 */
	function getApplicationListByMember($args)
	{
		if(isset($args->status_list) && $args->status_list !== '')
		{
			$output = executeQueryArray('poomahhi.getApplicationListByMemberStatusIn', $args);
		}
		elseif(isset($args->status) && $args->status !== '')
		{
			$output = executeQueryArray('poomahhi.getApplicationListByMember', $args);
		}
		else
		{
			$output = executeQueryArray('poomahhi.getApplicationListByMemberAll', $args);
		}
		return $output;
	}

	/**
	 * @brief 회원별 신청 상태별 건수 (품앗이 현황 대시보드용)
	 * @return object applied, selected, under_review, revision_requested, completed, rejected_cancelled
	 */
	function getApplicationStatusCountsByMember($member_srl)
	{
		$args = new stdClass();
		$args->member_srl = $member_srl;
		$output = executeQueryArray('poomahhi.getApplicationStatusCountsByMember', $args);
		$counts = (object)array(
			'applied' => 0,
			'selected' => 0,
			'under_review' => 0,
			'revision_requested' => 0,
			'completed' => 0,
			'rejected_cancelled' => 0,
		);
		if($output->toBool() && $output->data)
		{
			foreach($output->data as $row)
			{
				$cnt = (int)$row->cnt;
				if($row->status === 'rejected' || $row->status === 'cancelled')
				{
					$counts->rejected_cancelled += $cnt;
				}
				elseif(isset($counts->{$row->status}))
				{
					$counts->{$row->status} = $cnt;
				}
			}
		}
		return $counts;
	}

	/**
	 * @brief 상품별 신청 목록 조회
	 */
	function getApplicationsByProduct($args)
	{
		$output = executeQueryArray('poomahhi.getApplicationsByProduct', $args);
		return $output;
	}

	/**
	 * @brief 상품별 신청 상태별 건수
	 */
	function getApplicationStatusCountsByProduct($product_srl)
	{
		$args = new stdClass();
		$args->product_srl = $product_srl;
		$output = executeQueryArray('poomahhi.getApplicationStatusCountsByProduct', $args);
		$counts = (object)array(
			'applied' => 0,
			'selected' => 0,
			'under_review' => 0,
			'revision_requested' => 0,
			'completed' => 0,
			'rejected' => 0,
			'cancelled' => 0,
			'rejected_cancelled' => 0,
		);
		if($output->toBool() && $output->data)
		{
			$rows = $output->data;
			if(!is_array($rows))
			{
				$rows = array($rows);
			}
			foreach($rows as $row)
			{
				$cnt = (int)$row->cnt;
				if($row->status === 'rejected')
				{
					$counts->rejected = $cnt;
				}
				elseif($row->status === 'cancelled')
				{
					$counts->cancelled = $cnt;
				}
				elseif(isset($counts->{$row->status}))
				{
					$counts->{$row->status} = $cnt;
				}
			}
		}
		$counts->rejected_cancelled = $counts->rejected + $counts->cancelled;
		return $counts;
	}

	/**
	 * @brief 상품별 신청 목록 (상태 IN)
	 */
	function getApplicationsByProductStatusIn($args)
	{
		$output = executeQueryArray('poomahhi.getApplicationsByProductStatusIn', $args);
		return $output;
	}

	/**
	 * @brief 개설자 상품에 대한 신청 상태별 건수
	 * @return object applied, selected, under_review, revision_requested, completed, rejected_cancelled
	 */
	function getApplicationStatsByOrganizer($member_srl)
	{
		$args = new stdClass();
		$args->member_srl = $member_srl;
		$output = executeQueryArray('poomahhi.getApplicationStatsByOrganizer', $args);
		$counts = (object)array(
			'applied' => 0,
			'selected' => 0,
			'under_review' => 0,
			'revision_requested' => 0,
			'completed' => 0,
			'rejected_cancelled' => 0,
		);
		if($output->toBool() && $output->data)
		{
			foreach($output->data as $row)
			{
				$cnt = (int)$row->cnt;
				if($row->status === 'rejected' || $row->status === 'cancelled')
				{
					$counts->rejected_cancelled += $cnt;
				}
				elseif(isset($counts->{$row->status}))
				{
					$counts->{$row->status} = $cnt;
				}
			}
		}
		return $counts;
	}

	/**
	 * @brief 개설자 상품의 최근 신청 목록
	 */
	function getRecentApplicationsByOrganizer($args)
	{
		$output = executeQueryArray('poomahhi.getRecentApplicationsByOrganizer', $args);
		return $output;
	}

	/**
	 * @brief 신청 건수 조회
	 */
	function getApplicationCount($product_srl, $member_srl = null)
	{
		$args = new stdClass();
		$args->product_srl = $product_srl;
		if($member_srl) $args->member_srl = $member_srl;
		$output = executeQuery('poomahhi.getApplicationCount', $args);
		if(!$output->toBool() || !$output->data) return 0;
		return (int)$output->data->count;
	}

	/**
	 * @brief 활성 신청 건수 조회 (cancelled, rejected 제외)
	 * 중복 신청 확인용. 취소/미선정된 신청은 제외하여 재신청 가능하게 함
	 */
	function getActiveApplicationCount($product_srl, $member_srl)
	{
		$args = new stdClass();
		$args->product_srl = $product_srl;
		$args->member_srl = $member_srl;
		$output = executeQuery('poomahhi.getActiveApplicationCount', $args);
		if(!$output->toBool() || !$output->data) return 0;
		return (int)$output->data->count;
	}

	/**
	 * @brief 상품·회원 기준 활성 신청 1건 조회 (cancelled, rejected 제외, 최신 1건)
	 */
	function getActiveApplicationByProductAndMember($product_srl, $member_srl)
	{
		$args = new stdClass();
		$args->product_srl = $product_srl;
		$args->member_srl = $member_srl;
		$output = executeQueryArray('poomahhi.getActiveApplicationByProductAndMember', $args);
		if(!$output->toBool() || !$output->data || !count($output->data)) return null;
		return $output->data[0];
	}

	/**
	 * @brief 리뷰 단일 조회
	 */
	function getReview($review_srl)
	{
		$args = new stdClass();
		$args->review_srl = $review_srl;
		$output = executeQuery('poomahhi.getReview', $args);
		if(!$output->toBool() || !$output->data) return null;
		return $output->data;
	}

	/**
	 * @brief 신청건 기반 리뷰 조회
	 */
	function getReviewByApplication($application_srl)
	{
		$args = new stdClass();
		$args->application_srl = $application_srl;
		$output = executeQuery('poomahhi.getReviewByApplication', $args);
		if(!$output->toBool() || !$output->data) return null;
		return $output->data;
	}

	/**
	 * @brief 참여 인증 임시저장 조회
	 */
	function getReviewDraftByApplication($application_srl)
	{
		$args = new stdClass();
		$args->application_srl = $application_srl;
		$output = executeQuery('poomahhi.getReviewDraftByApplication', $args);
		if(!$output->toBool() || !$output->data) return null;
		return $output->data;
	}

	/**
	 * @brief 회원별 리뷰 목록
	 */
	function getReviewListByMember($args)
	{
		$output = executeQueryArray('poomahhi.getReviewListByMember', $args);
		return $output;
	}

	/**
	 * @brief 회원별 참여인증 리뷰 목록 (상품 정보 포함)
	 */
	function getReviewListByMemberWithProduct($args)
	{
		$output = executeQueryArray('poomahhi.getReviewListByMemberWithProduct', $args);
		return $output;
	}

	/**
	 * @brief 개설자 상품에 대한 리뷰 통계 (상품별 건수, 총 건수)
	 * @return object total_count, product_list (product_srl, product_title, review_count)
	 */
	function getReviewStatsByOrganizer($member_srl)
	{
		$args = new stdClass();
		$args->member_srl = $member_srl;
		$output = executeQueryArray('poomahhi.getReviewStatsByOrganizer', $args);
		$result = (object)array('total_count' => 0, 'product_list' => array());
		if($output->toBool() && $output->data)
		{
			$result->product_list = $output->data;
			foreach($output->data as $row)
			{
				$result->total_count += (int)$row->review_count;
			}
		}
		return $result;
	}

	/**
	 * @brief 개설자 상품의 최근 리뷰 목록
	 */
	function getRecentReviewsByOrganizer($args)
	{
		$output = executeQueryArray('poomahhi.getRecentReviewsByOrganizer', $args);
		return $output;
	}

	/**
	 * @brief 개설자 상품의 under_review 신청 중 회원평가 미작성 건 수
	 */
	function getMemberReviewPendingCountByOrganizer($member_srl)
	{
		$args = new stdClass();
		$args->member_srl = $member_srl;
		$output = executeQuery('poomahhi.getMemberReviewPendingCountByOrganizer', $args);
		if(!$output->toBool() || !$output->data) return 0;
		return (int)$output->data->pending_count;
	}

	/**
	 * @brief 개설자 상품별 찜(wishlist) 수
	 * @return array product_srl => wish_count
	 */
	function getWishlistCountByOrganizerProducts($member_srl)
	{
		$args = new stdClass();
		$args->member_srl = $member_srl;
		$output = executeQueryArray('poomahhi.getWishlistCountByOrganizerProducts', $args);
		$result = array();
		if($output->toBool() && $output->data)
		{
			foreach($output->data as $row)
			{
				$result[$row->product_srl] = (int)$row->wish_count;
			}
		}
		return $result;
	}

	/**
	 * @brief 참여인증 별점 분포 (member_srl 기준)
	 */
	function getReviewScoreDistribution($member_srl)
	{
		$args = new stdClass();
		$args->member_srl = $member_srl;
		$output = executeQuery('poomahhi.getReviewScoreDistribution', $args);
		if(!$output->toBool() || !$output->data)
		{
			return (object)array(
				'score_5_count' => 0, 'score_4_count' => 0, 'score_3_count' => 0,
				'score_2_count' => 0, 'score_1_count' => 0
			);
		}
		return $output->data;
	}

	/**
	 * @brief 참여인증 랭킹 (순위, 전체, 백분율)
	 */
	function getReviewRanking($member_srl)
	{
		$result = (object)array('position' => 0, 'total' => 0, 'percentile' => 0);

		$total_output = executeQuery('poomahhi.getReviewRankingTotal');
		if(!$total_output->toBool() || !$total_output->data) return $result;
		$result->total = (int)$total_output->data->total_count;
		if($result->total == 0) return $result;

		$stats = $this->getReviewStats($member_srl);
		$my_avg = (float)$stats->avg_score;
		if($my_avg == 0) { $result->position = $result->total; $result->percentile = 100; return $result; }

		$above_output = executeQueryArray('poomahhi.getReviewAvgByMember');
		if(!$above_output->toBool() || !$above_output->data) { $result->position = $result->total; $result->percentile = 100; return $result; }

		$above_count = 0;
		foreach($above_output->data as $row)
		{
			if((float)$row->avg_score > $my_avg) $above_count++;
		}

		$result->position = $above_count + 1;
		$result->percentile = round(($result->position / $result->total) * 100, 2);
		return $result;
	}

	/**
	 * @brief 회원별 리뷰 통계 (건수, 평균점수)
	 */
	function getReviewStats($member_srl)
	{
		$args = new stdClass();
		$args->member_srl = $member_srl;
		$output = executeQuery('poomahhi.getReviewStats', $args);
		if(!$output->toBool() || !$output->data) return (object)array('review_count' => 0, 'avg_score' => 0);
		return $output->data;
	}

	/**
	 * @brief 이행/미이행 통계
	 * - fulfilled_count: poomahhi_review 건수 (신청 삭제해도 유지)
	 * - unfulfilled_count: 현재 신청 중 미이행 + 삭제된 신청의 미이행 누적
	 */
	function getFulfillmentStats($member_srl)
	{
		$fulfilled = 0;
		$args = new stdClass();
		$args->member_srl = $member_srl;
		$fulfilled_out = executeQuery('poomahhi.getFulfilledCountByMember', $args);
		if($fulfilled_out->toBool() && $fulfilled_out->data && isset($fulfilled_out->data->fulfilled_count))
		{
			$fulfilled = (int)$fulfilled_out->data->fulfilled_count;
		}

		$unfulfilled_from_apps = 0;
		$unfulfilled_out = executeQuery('poomahhi.getUnfulfilledCountFromApplications', $args);
		if($unfulfilled_out->toBool() && $unfulfilled_out->data && isset($unfulfilled_out->data->unfulfilled_count))
		{
			$unfulfilled_from_apps = (int)$unfulfilled_out->data->unfulfilled_count;
		}

		$unfulfilled_extra = 0;
		$extra_out = executeQuery('poomahhi.getUnfulfilledExtraByMember', $args);
		if($extra_out->toBool() && $extra_out->data && isset($extra_out->data->unfulfilled_count))
		{
			$unfulfilled_extra = (int)$extra_out->data->unfulfilled_count;
		}

		return (object)array(
			'fulfilled_count' => $fulfilled,
			'unfulfilled_count' => $unfulfilled_from_apps + $unfulfilled_extra
		);
	}

	/**
	 * @brief 신청 삭제 시 미이행 누적 (삭제해도 미이행 횟수 유지)
	 */
	function addUnfulfilledExtra($member_srl)
	{
		$args = new stdClass();
		$args->member_srl = $member_srl;
		$existing = executeQuery('poomahhi.getUnfulfilledExtraByMember', $args);
		if($existing->toBool() && $existing->data && isset($existing->data->unfulfilled_count))
		{
			$new_count = (int)$existing->data->unfulfilled_count + 1;
			$up = new stdClass();
			$up->member_srl = $member_srl;
			$up->unfulfilled_count = $new_count;
			$up->last_update = date('YmdHis');
			return executeQuery('poomahhi.updateMemberFulfillmentExtraIncrement', $up);
		}
		$ins = new stdClass();
		$ins->member_srl = $member_srl;
		$ins->unfulfilled_count = 1;
		$ins->last_update = date('YmdHis');
		return executeQuery('poomahhi.insertMemberFulfillmentExtra', $ins);
	}

	/**
	 * @brief 신청이 미이행인지 여부 (삭제 전 체크용)
	 */
	function isApplicationUnfulfilled($application)
	{
		if(!$application || !in_array($application->status, array('selected', 'under_review', 'revision_requested'), true))
		{
			return false;
		}
		$oReview = $this->getReviewByApplication($application->application_srl);
		$no_review = !$oReview || !$oReview->review_srl;
		$deadline_passed = false;
		if(!empty($application->deadline) && strlen($application->deadline) >= 14)
		{
			$dl = substr($application->deadline, 0, 4) . '-' . substr($application->deadline, 4, 2) . '-' . substr($application->deadline, 6, 2) . ' ' . substr($application->deadline, 8, 2) . ':' . substr($application->deadline, 10, 2) . ':' . substr($application->deadline, 12, 2);
			$deadline_passed = (strtotime($dl) < time());
		}
		return $no_review || $deadline_passed;
	}

	/**
	 * @brief 받은 리뷰 목록 (target_member_srl 기준)
	 */
	function getMemberReviewListByTarget($args)
	{
		$output = executeQueryArray('poomahhi.getMemberReviewListByTarget', $args);
		return $output;
	}

	/**
	 * @brief 받은 리뷰 통계 (건수, 평균점수)
	 */
	function getMemberReviewStats($member_srl)
	{
		$args = new stdClass();
		$args->target_member_srl = $member_srl;
		$output = executeQuery('poomahhi.getMemberReviewStats', $args);
		if(!$output->toBool() || !$output->data) return (object)array('review_count' => 0, 'avg_score' => 0);
		return $output->data;
	}

	/**
	 * @brief 받은 리뷰 별점 분포
	 */
	function getMemberReviewScoreDistribution($member_srl)
	{
		$args = new stdClass();
		$args->target_member_srl = $member_srl;
		$output = executeQuery('poomahhi.getMemberReviewScoreDistribution', $args);
		if(!$output->toBool() || !$output->data)
		{
			return (object)array(
				'score_5_count' => 0, 'score_4_count' => 0, 'score_3_count' => 0,
				'score_2_count' => 0, 'score_1_count' => 0
			);
		}
		return $output->data;
	}

	/**
	 * @brief 받은 리뷰 랭킹 (순위, 전체, 백분율)
	 */
	function getMemberReviewRanking($member_srl)
	{
		$result = (object)array('position' => 0, 'total' => 0, 'percentile' => 0);

		$total_output = executeQuery('poomahhi.getMemberReviewRankingTotal');
		if(!$total_output->toBool() || !$total_output->data) return $result;
		$result->total = (int)$total_output->data->total_count;
		if($result->total == 0) return $result;

		$stats = $this->getMemberReviewStats($member_srl);
		$my_avg = (float)$stats->avg_score;
		if($my_avg == 0) { $result->position = $result->total; $result->percentile = 100; return $result; }

		$above_output = executeQueryArray('poomahhi.getMemberReviewAvgByTarget');
		if(!$above_output->toBool() || !$above_output->data) { $result->position = $result->total; $result->percentile = 100; return $result; }

		$above_count = 0;
		foreach($above_output->data as $row)
		{
			if((float)$row->avg_score > $my_avg) $above_count++;
		}

		$result->position = $above_count + 1;
		$result->percentile = round(($result->position / $result->total) * 100, 2);
		return $result;
	}

	/**
	 * @brief 리뷰 답변 목록
	 */
	function getReviewReplies($review_srl)
	{
		$args = new stdClass();
		$args->review_srl = $review_srl;
		$output = executeQueryArray('poomahhi.getReviewReplies', $args);
		if(!$output->toBool()) return array();
		return $output->data ?: array();
	}

	/**
	 * @brief 회원 리뷰(받은 리뷰) 단일 조회
	 */
	function getMemberReview($review_srl)
	{
		$args = new stdClass();
		$args->review_srl = $review_srl;
		$output = executeQuery('poomahhi.getMemberReview', $args);
		if(!$output->toBool() || !$output->data) return null;
		return $output->data;
	}

	/**
	 * @brief 신청건에 대한 회원 평가 조회
	 */
	function getMemberReviewByApplication($application_srl)
	{
		$args = new stdClass();
		$args->application_srl = $application_srl;
		$output = executeQuery('poomahhi.getMemberReviewByApplication', $args);
		if(!$output->toBool() || !$output->data) return null;
		return $output->data;
	}

	/**
	 * @brief 위시리스트 목록
	 */
	function getWishlist($args)
	{
		$output = executeQueryArray('poomahhi.getWishlist', $args);
		return $output;
	}

	/**
	 * @brief 위시리스트 항목 존재 여부
	 */
	function getWishlistItem($member_srl, $product_srl)
	{
		$args = new stdClass();
		$args->member_srl = $member_srl;
		$args->product_srl = $product_srl;
		$output = executeQuery('poomahhi.getWishlistItem', $args);
		if(!$output->toBool() || !$output->data) return null;
		return $output->data;
	}

	/**
	 * @brief 카테고리 목록 (Rhymix 내장 document 카테고리 시스템 사용)
	 */
	function getCategoryList($module_srl = null)
	{
		if(!$module_srl) return array();
		$oDocumentModel = getModel('document');
		$category_list = $oDocumentModel->getCategoryList($module_srl);
		return $category_list ?: array();
	}

	/**
	 * @brief 카테고리 단일 조회 (Rhymix 내장 document 카테고리 시스템 사용)
	 */
	function getCategory($category_srl)
	{
		if(!$category_srl) return null;
		$oDocumentModel = getModel('document');
		$category = $oDocumentModel->getCategory($category_srl);
		return $category ?: null;
	}

	/**
	 * @brief 확장변수 정의 목록
	 */
	function getExtraDefList($module_srl = null)
	{
		$args = new stdClass();
		if($module_srl) $args->module_srl = $module_srl;
		$output = executeQueryArray('poomahhi.getExtraDefList', $args);
		if(!$output->toBool()) return array();
		return $output->data ?: array();
	}

	/**
	 * @brief 확장변수 정의 단일 조회
	 */
	function getExtraDef($extra_def_srl)
	{
		$args = new stdClass();
		$args->extra_def_srl = $extra_def_srl;
		$output = executeQuery('poomahhi.getExtraDef', $args);
		if(!$output->toBool() || !$output->data) return null;
		return $output->data;
	}

	/**
	 * @brief 확장변수 템플릿 목록
	 */
	function getExtraTemplateList($module_srl = null)
	{
		if(!$module_srl) return array();
		try
		{
			$args = new stdClass();
			$args->module_srl = $module_srl;
			$output = executeQueryArray('poomahhi.getExtraTemplateList', $args);
			if(!$output->toBool()) return array();
			return $output->data ?: array();
		}
		catch(\Exception $e)
		{
			return array();
		}
	}

	/**
	 * @brief 확장변수 템플릿 단일 조회
	 */
	function getExtraTemplate($template_srl)
	{
		if(!$template_srl) return null;
		try
		{
			$args = new stdClass();
			$args->template_srl = $template_srl;
			$output = executeQuery('poomahhi.getExtraTemplate', $args);
			if(!$output->toBool() || !$output->data) return null;
			return $output->data;
		}
		catch(\Exception $e)
		{
			return null;
		}
	}

	/**
	 * @brief 정산 - 비즈니스 본인 상품별
	 */
	function getSettlementByBusiness($args)
	{
		$output = executeQueryArray('poomahhi.getSettlementByBusiness', $args);
		return $output;
	}

	/**
	 * @brief 개설자 본인 상품별 신청·선정·완료 집계 (상품 regdate 필터 없음, 관리 목록용)
	 */
	function getProductApplicationStatsByMember($args)
	{
		$output = executeQueryArray('poomahhi.getProductApplicationStatsByMember', $args);
		return $output;
	}

	/**
	 * @brief 정산 - 전체 비즈니스별 (어드민)
	 */
	function getSettlementAllBusiness($args)
	{
		$output = executeQueryArray('poomahhi.getSettlementAllBusiness', $args);
		return $output;
	}

	/**
	 * @brief 쇼핑채널 목록
	 */
	function getChannelList($module_srl = null)
	{
		if(!$module_srl) return array();
		try
		{
			$args = new stdClass();
			$args->module_srl = $module_srl;
			$output = executeQueryArray('poomahhi.getChannelList', $args);
			if(!$output->toBool()) return array();
			return $output->data ?: array();
		}
		catch(\Exception $e)
		{
			return array();
		}
	}

	/**
	 * @brief 쇼핑채널 단일 조회
	 */
	function getChannel($channel_srl)
	{
		if(!$channel_srl) return null;
		try
		{
			$args = new stdClass();
			$args->channel_srl = $channel_srl;
			$output = executeQuery('poomahhi.getChannel', $args);
			if(!$output->toBool() || !$output->data) return null;
			return $output->data;
		}
		catch(\Exception $e)
		{
			return null;
		}
	}

	/**
	 * @brief 지역 목록
	 */
	function getRegionList($module_srl = null)
	{
		if(!$module_srl) return array();
		try
		{
			$args = new stdClass();
			$args->module_srl = $module_srl;
			$output = executeQueryArray('poomahhi.getRegionList', $args);
			if(!$output->toBool()) return array();
			return $output->data ?: array();
		}
		catch(\Exception $e)
		{
			return array();
		}
	}

	/**
	 * @brief 지역 단일 조회
	 */
	function getRegion($region_srl)
	{
		if(!$region_srl) return null;
		try
		{
			$args = new stdClass();
			$args->region_srl = $region_srl;
			$output = executeQuery('poomahhi.getRegion', $args);
			if(!$output->toBool() || !$output->data) return null;
			return $output->data;
		}
		catch(\Exception $e)
		{
			return null;
		}
	}

	/**
	 * @brief 회원 포인트 요약 (전체 보유/누적/적립 예정)
	 */
	function getMyPointSummary($member_srl)
	{
		$args = new stdClass();
		$args->member_srl = $member_srl;
		$output = executeQuery('poomahhi.getMemberPoint', $args);
		if(!$output->toBool() || !$output->data)
		{
			return (object)array(
				'point' => 0,
				'accumulated_point' => 0,
				'pending_point' => 0,
			);
		}
		$row = $output->data;
		return (object)array(
			'point' => (int)$row->point,
			'accumulated_point' => (int)$row->accumulated_point,
			'pending_point' => (int)$row->pending_point,
		);
	}

	/**
	 * @brief 포인트 거래 내역 목록 (member_srl, type, year, month, page)
	 */
	function getPointLogList($args)
	{
		$year = isset($args->year) ? (int)$args->year : (int)date('Y');
		$month = isset($args->month) ? (int)$args->month : (int)date('n');
		$start_date = sprintf('%04d%02d01000000', $year, $month);
		$end_date = sprintf('%04d%02d%02d235959', $year, $month, date('t', mktime(0, 0, 0, $month, 1, $year)));

		$query_args = new stdClass();
		$query_args->member_srl = $args->member_srl;
		$query_args->start_date = $start_date;
		$query_args->end_date = $end_date;
		$query_args->page = isset($args->page) ? (int)$args->page : 1;
		$query_args->list_count = isset($args->list_count) ? (int)$args->list_count : 20;
		$query_args->page_count = isset($args->page_count) ? (int)$args->page_count : 10;

		if(isset($args->type) && $args->type !== '' && in_array($args->type, array('earn', 'deduct')))
		{
			$query_args->type = $args->type;
			$output = executeQueryArray('poomahhi.getPointLogList', $query_args);
		}
		else
		{
			$output = executeQueryArray('poomahhi.getPointLogListAll', $query_args);
		}
		return $output;
	}

	/**
	 * @brief 모듈 설정 가져오기
	 */
	function getModuleConfig()
	{
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('poomahhi');
		if(!$config) $config = new stdClass();
		if(!isset($config->max_certification_images)) $config->max_certification_images = 10;
		if(!isset($config->max_purchase_images)) $config->max_purchase_images = 10;
		if(!isset($config->review_deadline_days)) $config->review_deadline_days = 14;
		if(!isset($config->default_list_count)) $config->default_list_count = 20;
		if(!isset($config->privacy_content)) $config->privacy_content = '';
		if(!isset($config->content_point_type)) $config->content_point_type = 'poomahhi';
		return $config;
	}
}
