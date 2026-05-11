<?php
/**
 * @class popular_docs
 * @brief 지금인기/주간인기/월간인기 순위 위젯
 */
class popular_docs extends WidgetHandler
{
	const NAME = 'popular_docs';

	/**
	 * 순위 스냅샷: document_srl => array('rank'=>int, 'voted'=>int)
	 */
	protected static function _popularDocsSnapshotFromList($list)
	{
		$snap = array();
		foreach ($list as $idx => $doc)
		{
			$dsrl = isset($doc->document_srl) ? (int) $doc->document_srl : 0;
			if ($dsrl < 1)
			{
				continue;
			}
			$snap[$dsrl] = array(
				'rank' => $idx + 1,
				'voted' => isset($doc->voted_count) ? (int) $doc->voted_count : 0,
			);
		}
		return $snap;
	}

	/**
	 * 두 스냅샷이 동일한지 (문서 집합·순위·추천수)
	 */
	protected static function _popularDocsSnapshotsEqual($a, $b)
	{
		if (!is_array($a) || !is_array($b) || count($a) !== count($b))
		{
			return false;
		}
		foreach ($a as $dsrl => $row)
		{
			if (!isset($b[$dsrl]))
			{
				return false;
			}
			if ((int) $b[$dsrl]['rank'] !== (int) $row['rank'])
			{
				return false;
			}
			if ((int) $b[$dsrl]['voted'] !== (int) $row['voted'])
			{
				return false;
			}
		}
		return true;
	}

	/**
	 * 이전 스냅샷 대비 화살표: 1=up, 2=down, 3=same(동률)
	 */
	protected static function _popularDocsComputeArrowMap($list, $old_ranks)
	{
		$map = array();
		foreach ($list as $idx => $doc)
		{
			$dsrl = isset($doc->document_srl) ? (int) $doc->document_srl : 0;
			if ($dsrl < 1)
			{
				continue;
			}
			$cur_rank = $idx + 1;
			$cur_voted = isset($doc->voted_count) ? (int) $doc->voted_count : 0;
			if (!isset($old_ranks[$dsrl]))
			{
				$map[(string) $dsrl] = 1;
				continue;
			}
			$old_rank = (int) $old_ranks[$dsrl]['rank'];
			$old_voted = (int) $old_ranks[$dsrl]['voted'];
			if ($old_voted < 0)
			{
				if ($old_rank > $cur_rank)
				{
					$map[(string) $dsrl] = 1;
				}
				elseif ($old_rank < $cur_rank)
				{
					$map[(string) $dsrl] = 2;
				}
				else
				{
					$map[(string) $dsrl] = 3;
				}
			}
			elseif ($old_rank > $cur_rank)
			{
				$map[(string) $dsrl] = 1;
			}
			elseif ($old_rank < $cur_rank)
			{
				$map[(string) $dsrl] = 2;
			}
			elseif ($cur_voted > $old_voted)
			{
				$map[(string) $dsrl] = 1;
			}
			elseif ($cur_voted < $old_voted)
			{
				$map[(string) $dsrl] = 2;
			}
			else
			{
				$map[(string) $dsrl] = 3;
			}
		}
		return $map;
	}

	/**
	 * 캐시 JSON에서 ranks 정규화 (키 int, 레거시 baseline_ranks 지원)
	 */
	protected static function _popularDocsNormalizeStoredRanks($decoded)
	{
		if (!is_array($decoded))
		{
			return array();
		}
		$raw = null;
		if (isset($decoded['ranks']) && is_array($decoded['ranks']))
		{
			$raw = $decoded['ranks'];
		}
		elseif (isset($decoded['baseline_ranks']) && is_array($decoded['baseline_ranks']))
		{
			$raw = $decoded['baseline_ranks'];
		}
		if (!is_array($raw))
		{
			return array();
		}
		$out = array();
		foreach ($raw as $k => $entry)
		{
			$dsrl = (int) $k;
			if ($dsrl < 1)
			{
				continue;
			}
			if (is_array($entry))
			{
				$out[$dsrl] = array(
					'rank' => isset($entry['rank']) ? (int) $entry['rank'] : 0,
					'voted' => isset($entry['voted']) ? (int) $entry['voted'] : 0,
				);
			}
			else
			{
				$out[$dsrl] = array('rank' => (int) $entry, 'voted' => -1);
			}
		}
		return $out;
	}

	protected static function _popularDocsNormalizeStoredArrows($decoded)
	{
		if (!is_array($decoded) || !isset($decoded['arrows']) || !is_array($decoded['arrows']))
		{
			return array();
		}
		$out = array();
		foreach ($decoded['arrows'] as $k => $v)
		{
			$out[(string) (int) $k] = (int) $v;
		}
		return $out;
	}

	/**
	 * @param string $cache_scope 위젯이 게시판을 지정하지 않았으면 'all', 지정 시 'm:1,2,3'
	 */
	protected static function _popularDocsRankCachePath($period, $cache_scope, $list_count)
	{
		if (!defined('RX_BASEDIR'))
		{
			return '';
		}
		$date_key = date('Ymd');
		$id = md5($period . ':' . $date_key . '|' . (string) $cache_scope . '|' . (int) $list_count);
		return rtrim(RX_BASEDIR, '/') . '/files/cache/widget_popular_docs_ranks_' . $id . '.json';
	}

	protected static function _popularDocsWriteRankCache($path, array $ranks, array $arrows)
	{
		if ($path === '' || !is_writable(dirname($path)))
		{
			return false;
		}
		$ranks_out = array();
		foreach ($ranks as $dsrl => $row)
		{
			$ranks_out[(string) (int) $dsrl] = array(
				'rank' => (int) $row['rank'],
				'voted' => (int) $row['voted'],
			);
		}
		$arrows_out = array();
		foreach ($arrows as $k => $v)
		{
			$arrows_out[(string) (int) $k] = (int) $v;
		}
		$payload = json_encode(array(
			'saved_at' => time(),
			'ranks' => $ranks_out,
			'arrows' => $arrows_out,
		));
		return @file_put_contents($path, $payload, LOCK_EX) !== false;
	}

	function proc($args)
	{
		$args = (object) $args;
		$period = Context::get('period') ?: ($args->period ?? 'today');
		if (!in_array($period, array('today', 'week', 'month'), true))
		{
			$period = 'today';
		}

		$list_count = isset($args->list_count) ? (int) $args->list_count : 10;
		if ($list_count < 1) $list_count = 10;

		$module_srl_list = array();
		$module_srl_src = $args->module_srl_list ?? $args->module_srl ?? null;
		$user_picked_boards = !empty($module_srl_src);
		if (!empty($module_srl_src))
		{
			if (is_array($module_srl_src))
			{
				foreach ($module_srl_src as $item)
				{
					if (is_numeric($item))
					{
						$srl = (int) $item;
					}
					elseif (is_object($item) && isset($item->module_srl))
					{
						$srl = (int) $item->module_srl;
					}
					elseif (is_array($item) && isset($item['module_srl']))
					{
						$srl = (int) $item['module_srl'];
					}
					else
					{
						continue;
					}
					if ($srl > 0) $module_srl_list[] = $srl;
				}
				$module_srl_list = array_values(array_unique($module_srl_list));
			}
			elseif (is_string($module_srl_src) && trim($module_srl_src) !== '')
			{
				$oModuleModel = getModel('module');
				$parts = array_map('trim', explode(',', $module_srl_src));
				foreach ($parts as $part)
				{
					if ($part === '') continue;
					if (is_numeric($part))
					{
						$module_srl_list[] = (int) $part;
					}
					else
					{
						$mod = $oModuleModel->getModuleInfoByMid($part);
						if ($mod && isset($mod->module_srl) && isset($mod->module) && $mod->module === 'board')
						{
							$module_srl_list[] = (int) $mod->module_srl;
						}
					}
				}
			}
		}
		if (empty($module_srl_list))
		{
			$oModuleModel = getModel('module');
			$mid_list = $oModuleModel->getMidList(null, array('module_srl', 'mid', 'browser_title', 'module'));
			foreach ($mid_list as $m)
			{
				if (isset($m->module) && $m->module === 'board')
				{
					$module_srl_list[] = (int) $m->module_srl;
				}
			}
		}
		if (empty($module_srl_list))
		{
			$board_args = new stdClass();
			$board_args->module = 'board';
			$board_output = executeQueryArray('popular_docs.getBoardModuleSrlList', $board_args);
			if ($board_output->toBool() && !empty($board_output->data))
			{
				foreach ($board_output->data as $row)
				{
					$srl = isset($row->module_srl) ? (int) $row->module_srl : 0;
					if ($srl > 0) $module_srl_list[] = $srl;
				}
			}
			if (empty($module_srl_list))
			{
				$module_srl_list = array(0);
			}
		}

		if ($user_picked_boards)
		{
			$cache_mod = array_values(array_filter(array_map('intval', $module_srl_list), function ($n) {
				return $n > 0;
			}));
			sort($cache_mod, SORT_NUMERIC);
			$rank_cache_scope = 'm:' . implode(',', $cache_mod);
		}
		else
		{
			$rank_cache_scope = 'all';
		}

		$current_mid = Context::get('mid');
		if ($current_mid)
		{
			$oModuleModel = getModel('module');
			$current_mod = $oModuleModel->getModuleInfoByMid($current_mid);
			if ($current_mod && isset($current_mod->module) && $current_mod->module === 'board' && isset($current_mod->module_srl) && $current_mod->module_srl > 0)
			{
				$current_srl = (int) $current_mod->module_srl;
				if (!in_array($current_srl, $module_srl_list))
				{
					$module_srl_list[] = $current_srl;
				}
			}
		}

		if ($period === 'today')
		{
			$start_regdate = date('Ymd') . '000000';
			$end_regdate = date('Ymd') . '235959';
		}
		elseif ($period === 'week')
		{
			$end_ts = strtotime('today 23:59:59');
			$start_ts = strtotime('-6 days', strtotime('today'));
			$start_regdate = date('Ymd', $start_ts) . '000000';
			$end_regdate = date('Ymd', $end_ts) . '235959';
		}
		else
		{
			$start_regdate = date('Ym') . '01000000';
			$end_regdate = date('Ymt') . '235959';
		}

		$rank_cache_path = self::_popularDocsRankCachePath($period, $rank_cache_scope, $list_count);
		$stored_ranks = array();
		$stored_arrows = array();
		$decoded_cache = null;
		if ($rank_cache_path !== '' && is_readable($rank_cache_path))
		{
			$raw = @file_get_contents($rank_cache_path);
			$decoded_cache = is_string($raw) ? json_decode($raw, true) : null;
			if (is_array($decoded_cache))
			{
				$stored_ranks = self::_popularDocsNormalizeStoredRanks($decoded_cache);
				$stored_arrows = self::_popularDocsNormalizeStoredArrows($decoded_cache);
			}
		}

		$doc_args = new stdClass();
		$doc_args->module_srl = $module_srl_list;
		$doc_args->statusList = array('PUBLIC', 'SECRET');
		$doc_args->sort_index = 'voted_count';
		$doc_args->order_type = 'desc';
		$doc_args->list_count = $list_count;
		$doc_args->page = 1;
		$doc_args->start_regdate = $start_regdate;
		$doc_args->end_regdate = $end_regdate;

		$columnList = array('document_srl', 'module_srl', 'title', 'nick_name', 'voted_count', 'regdate');
		$output = DocumentModel::getDocumentList($doc_args, false, false, $columnList);
		$list = array();
		if ($output->toBool() && !empty($output->data))
		{
			$oModuleModel = getModel('module');
			$mid_cache = array();
			foreach (array_values($output->data) as $doc)
			{
				$doc = is_object($doc) && method_exists($doc, 'get') ? (object) array(
					'document_srl' => $doc->get('document_srl'),
					'module_srl' => $doc->get('module_srl'),
					'title' => $doc->get('title'),
					'nick_name' => $doc->get('nick_name'),
					'voted_count' => $doc->get('voted_count'),
					'regdate' => $doc->get('regdate'),
				) : (object) (array) $doc;
				$voted = isset($doc->voted_count) ? (int) $doc->voted_count : 0;
				if ($voted < 1) continue;
				$msrl = isset($doc->module_srl) ? (int) $doc->module_srl : 0;
				if ($msrl > 0)
				{
					if (!isset($mid_cache[$msrl]))
					{
						$mod = $oModuleModel->getModuleInfoByModuleSrl($msrl);
						$mid_cache[$msrl] = ($mod && isset($mod->mid)) ? $mod->mid : '';
					}
					$doc->board_mid = $mid_cache[$msrl];
				}
				else
				{
					$doc->board_mid = '';
				}
				$list[] = $doc;
			}
		}

		if (count($list) > 1)
		{
			usort($list, function ($a, $b) {
				$va = isset($a->voted_count) ? (int) $a->voted_count : 0;
				$vb = isset($b->voted_count) ? (int) $b->voted_count : 0;
				if ($va !== $vb)
				{
					return $vb <=> $va;
				}
				$sa = isset($a->document_srl) ? (int) $a->document_srl : 0;
				$sb = isset($b->document_srl) ? (int) $b->document_srl : 0;
				return $sb <=> $sa;
			});
		}

		$current_ranks = self::_popularDocsSnapshotFromList($list);
		$rank_arrow_map = array();
		$cache_action = 'none';

		if (empty($stored_ranks))
		{
			foreach ($current_ranks as $dsrl => $_)
			{
				$rank_arrow_map[(string) $dsrl] = 0;
			}
			$cache_action = self::_popularDocsWriteRankCache($rank_cache_path, $current_ranks, $rank_arrow_map) ? 'init' : 'init_failed';
		}
		elseif (self::_popularDocsSnapshotsEqual($stored_ranks, $current_ranks))
		{
			foreach ($current_ranks as $dsrl => $_)
			{
				$key = (string) $dsrl;
				$rank_arrow_map[$key] = isset($stored_arrows[$key]) ? (int) $stored_arrows[$key] : 0;
			}
			$cache_action = 'reuse_arrows';
		}
		else
		{
			$rank_arrow_map = self::_popularDocsComputeArrowMap($list, $stored_ranks);
			$cache_action = self::_popularDocsWriteRankCache($rank_cache_path, $current_ranks, $rank_arrow_map) ? 'recalc_save' : 'recalc_save_failed';
		}

		debugPrint('[PD]', $period, $rank_cache_scope, $cache_action, 'arrows', $rank_arrow_map);

		$skin_path = $this->widget_path . 'skins/' . (isset($args->skin) ? $args->skin : 'default') . '/';

		Context::set('list', $list);
		Context::set('rank_arrow_map', $rank_arrow_map);
		Context::set('period', $period);
		Context::set('widget_args', $args);
		Context::set('widget_page_mid', Context::get('mid'));

		$oTemplate = new Rhymix\Framework\Template($skin_path, 'index');
		return $oTemplate->compile();
	}
}
