<?php
/**
 * @class best_product_poomahhi
 * @brief 베스트 상품 품앗이 위젯 (찜 수 많은 순)
 */
class best_product_poomahhi extends WidgetHandler
{
	const NAME = 'best_product_poomahhi';

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
		$poomahhi_mid = $mod && isset($mod->mid) ? $mod->mid : '';

		$list_count = isset($args->list_count) ? (int) $args->list_count : 9;
		if ($list_count < 1) $list_count = 9;

		self::_applyPoomahhiThumbnailWidgetArgs($args);

		$list = array();
		$wishlist_map = array();
		$logged_info = Context::get('logged_info');
		$today = new DateTime('today');

		if ($module_srl > 0)
		{
			$query_args = new stdClass();
			$query_args->module_srl = $module_srl;
			$query_args->product_type = 'product';
			$query_args->status = 'active';
			$query_args->list_count = $list_count;
			$query_args->page = 1;
			$query_args->page_count = 1;

			$oModel = getModel('poomahhi');
			$output = $oModel->getProductListByWishlistCount($query_args);
			if ($output->toBool() && !empty($output->data))
			{
				$list = array_values($output->data);
			}

			if ($list && $logged_info)
			{
				foreach ($list as $product)
				{
					$wish_item = $oModel->getWishlistItem($logged_info->member_srl, $product->product_srl);
					if ($wish_item) $wishlist_map[$product->product_srl] = true;
				}
			}

			foreach ($list as &$product)
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
				$product->is_paid = (($product->content_access_type ?: 'public') === 'paid' && (int)($product->point_cost ?: 0) > 0);
				$product->point_cost_display = (int)($product->point_cost ?: 0);
			}
		}

		$skin_path = $this->widget_path . 'skins/' . (isset($args->skin) ? $args->skin : 'default') . '/';

		Context::set('list', $list);
		Context::set('wishlist_map', $wishlist_map);
		Context::set('widget_args', $args);
		Context::set('logged_info', Context::get('logged_info'));
		Context::set('poomahhi_mid', $poomahhi_mid);
		Context::set('widget_page_mid', Context::get('mid'));

		$oTemplate = new Rhymix\Framework\Template($skin_path, 'index');
		return $oTemplate->compile();
	}

	/**
	 * 위젯 썸네일 표시 옵션(thumbnail_aspect, thumbnail_max_height) 검증 및 템플릿용 필드 설정
	 */
	private static function _applyPoomahhiThumbnailWidgetArgs($args)
	{
		$raw = isset($args->thumbnail_aspect) ? strtolower(trim((string) $args->thumbnail_aspect)) : '';
		$allowed = array(
			'1_1' => array('css' => '1 / 1', 'auto' => false),
			'4_3' => array('css' => '4 / 3', 'auto' => false),
			'3_4' => array('css' => '3 / 4', 'auto' => false),
			'16_9' => array('css' => '16 / 9', 'auto' => false),
			'auto' => array('css' => 'auto', 'auto' => true),
		);
		if ($raw === '' || !isset($allowed[$raw]))
		{
			$raw = '1_1';
		}
		$sel = $allowed[$raw];
		$args->thumbnail_aspect = $raw;
		$args->thumbnail_aspect_css = $sel['css'];
		$args->thumbnail_aspect_is_auto = $sel['auto'];

		$args->thumbnail_max_height_px = 0;
		$mh = isset($args->thumbnail_max_height) ? trim((string) $args->thumbnail_max_height) : '';
		if ($mh !== '' && ctype_digit($mh))
		{
			$n = (int) $mh;
			if ($n > 0)
			{
				if ($n > 2000) $n = 2000;
				$args->thumbnail_max_height_px = $n;
			}
		}
	}
}
