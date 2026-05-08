<?php
/**
 * @class region_poomahhi
 * @brief 지역 품앗이 위젯 (지역별 품앗이 상품 표시)
 */
class region_poomahhi extends WidgetHandler
{
	const NAME = 'region_poomahhi';

	function proc($args)
	{
		$args = (object) $args;

		$oModuleModel = getModel('module');
		$module_srl = 0;
		$module_srl_src = $args->module_srl_list ?? $args->module_srl ?? null;
		if (!empty($module_srl_src))
		{
			if (is_array($module_srl_src))
			{
				$arr = array_filter(array_map('intval', $module_srl_src));
				$module_srl = !empty($arr) ? (int) reset($arr) : 0;
			}
			elseif (is_string($module_srl_src) && trim($module_srl_src) !== '')
			{
				$parts = array_map('trim', explode(',', $module_srl_src));
				$arr = array_filter(array_map('intval', $parts));
				$module_srl = !empty($arr) ? (int) reset($arr) : 0;
			}
			else
			{
				$module_srl = (int) $module_srl_src;
			}
		}
		if ($module_srl < 1)
		{
			$mid_list = $oModuleModel->getMidList(null, array('module_srl', 'mid', 'module'));
			foreach ($mid_list as $m)
			{
				if (isset($m->module) && $m->module === 'poomahhi')
				{
					$module_srl = (int) $m->module_srl;
					break;
				}
			}
		}
		$mod = $module_srl > 0 ? $oModuleModel->getModuleInfoByModuleSrl($module_srl) : null;
		if ($mod && (!isset($mod->module) || $mod->module !== 'poomahhi'))
		{
			$module_srl = 0;
			$mod = null;
		}
		if ($module_srl < 1 || !$mod)
		{
			$mid_list = $oModuleModel->getMidList(null, array('module_srl', 'mid', 'module'));
			foreach ($mid_list as $m)
			{
				if (isset($m->module) && $m->module === 'poomahhi')
				{
					$module_srl = (int) $m->module_srl;
					$mod = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
					break;
				}
			}
		}
		$poomahhi_mid = $mod && isset($mod->mid) ? $mod->mid : '';

		$list_count = isset($args->list_count) ? (int) $args->list_count : 6;
		if ($list_count < 1) $list_count = 6;

		$region_srl = Context::get('region_srl');
		$region_srl = $region_srl !== null && $region_srl !== '' ? (int) $region_srl : 0;

		$region_list = array();
		$product_list = array();
		$region_map = array();
		$wishlist_map = array();
		$logged_info = Context::get('logged_info');
		$today = new DateTime('today');

		if ($module_srl > 0)
		{
			$oModel = getModel('poomahhi');
			// 지역 목록: 품앗이 모듈 관리자에서 설정한 값(poomahhi_region 테이블)을 사용
			$region_list = $oModel->getRegionList($module_srl);
			if ($region_list)
			{
				$region_list = array_values($region_list);
				foreach ($region_list as $rg)
				{
					$region_map[$rg->region_srl] = $rg->title;
				}
			}
			if (!$region_list)
			{
				$local_mod = $oModuleModel->getModuleInfoByMid('local_poomahhi');
				if ($local_mod && isset($local_mod->module_srl) && (int) $local_mod->module_srl !== $module_srl)
				{
					$module_srl = (int) $local_mod->module_srl;
					$mod = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
					$poomahhi_mid = $mod && isset($mod->mid) ? $mod->mid : '';
					$region_list = $oModel->getRegionList($module_srl);
					if ($region_list)
					{
						$region_list = array_values($region_list);
						$region_map = array();
						foreach ($region_list as $rg)
						{
							$region_map[$rg->region_srl] = $rg->title;
						}
					}
				}
			}
			if (!$region_list)
			{
				$mid_list = $oModuleModel->getMidList(null, array('module_srl', 'mid', 'module'));
				foreach ($mid_list as $m)
				{
					if (!isset($m->module) || $m->module !== 'poomahhi') continue;
					$cand_srl = (int) $m->module_srl;
					if ($cand_srl === $module_srl) continue;
					$cand_regions = $oModel->getRegionList($cand_srl);
					if ($cand_regions && count($cand_regions) > 0)
					{
						$module_srl = $cand_srl;
						$mod = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
						$poomahhi_mid = $mod && isset($mod->mid) ? $mod->mid : '';
						$region_list = array_values($cand_regions);
						$region_map = array();
						foreach ($region_list as $rg)
						{
							$region_map[$rg->region_srl] = $rg->title;
						}
						break;
					}
				}
			}

			if ($region_srl === 0 && $region_list)
			{
				$region_srl = (int) $region_list[0]->region_srl;
			}

			if ($region_srl > 0)
			{
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
				{
					$product_list = array_values($product_output->data);
				}

				if ($product_list && $logged_info)
				{
					foreach ($product_list as $product)
					{
						$wish_item = $oModel->getWishlistItem($logged_info->member_srl, $product->product_srl);
						if ($wish_item) $wishlist_map[$product->product_srl] = true;
					}
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
					{
						$product->content_summary = $product->short_description;
					}
					elseif (!empty($product->content))
					{
						$product->content_summary = mb_strimwidth(strip_tags($product->content), 0, 80, '...');
					}

					if ($product->region_srl && isset($region_map[$product->region_srl]))
					{
						$product->region_title = $region_map[$product->region_srl];
					}
					$product->is_paid = (($product->content_access_type ?: 'public') === 'paid' && (int)($product->point_cost ?: 0) > 0);
					$product->point_cost_display = (int)($product->point_cost ?: 0);
				}
			}
		}

		$skin_path = $this->widget_path . 'skins/' . (isset($args->skin) ? $args->skin : 'default') . '/';

		Context::set('region_list', $region_list);
		Context::set('product_list', $product_list);
		Context::set('region_srl', $region_srl);
		Context::set('wishlist_map', $wishlist_map);
		Context::set('widget_args', $args);
		Context::set('poomahhi_mid', $poomahhi_mid);
		Context::set('widget_page_mid', Context::get('mid'));
		Context::set('logged_info', Context::get('logged_info'));

		$oTemplate = new Rhymix\Framework\Template($skin_path, 'index');
		return $oTemplate->compile();
	}
}
