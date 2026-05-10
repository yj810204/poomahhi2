<?php
/**
 * @class popular_docs
 * @brief 지금인기/주간인기/월간인기 순위 위젯
 */
class popular_docs extends WidgetHandler
{
	const NAME = 'popular_docs';

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

		$skin_path = $this->widget_path . 'skins/' . (isset($args->skin) ? $args->skin : 'default') . '/';

		Context::set('list', $list);
		Context::set('period', $period);
		Context::set('widget_args', $args);
		Context::set('widget_page_mid', Context::get('mid'));

		$oTemplate = new Rhymix\Framework\Template($skin_path, 'index');
		return $oTemplate->compile();
	}
}
