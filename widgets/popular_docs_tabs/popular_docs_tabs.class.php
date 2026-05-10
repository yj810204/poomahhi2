<?php
/**
 * @class popular_docs_tabs
 * @brief 3개 게시판 탭형 인기 순위 위젯 (추천수 기준)
 */
class popular_docs_tabs extends WidgetHandler
{
	const NAME = 'popular_docs_tabs';

	function proc($args)
	{
		$args = (object) $args;

		$list_count = isset($args->list_count) ? (int) $args->list_count : 10;
		if ($list_count < 1) $list_count = 10;

		$oModuleModel = getModel('module');
		$oPoomahhiModel = getModel('poomahhi');

		$board_configs = array();
		foreach (array('board_1', 'board_2', 'board_3') as $key)
		{
			$var_key = $key . '_module_srl_list';
			$module_srl = 0;
			$src = $args->$var_key ?? null;
			if (!empty($src))
			{
				if (is_array($src))
				{
					$arr = array_filter(array_map('intval', $src));
					$module_srl = !empty($arr) ? (int) reset($arr) : 0;
				}
				elseif (is_string($src) && trim($src) !== '')
				{
					$parts = array_map('trim', explode(',', $src));
					$arr = array_filter(array_map('intval', $parts));
					$module_srl = !empty($arr) ? (int) reset($arr) : 0;
				}
				else
				{
					$module_srl = (int) $src;
				}
			}
			$board_configs[] = array('module_srl' => $module_srl, 'key' => $key);
		}

		$tabs = array();
		$tab_index = 0;
		foreach ($board_configs as $cfg)
		{
			$tab_index++;
			$module_srl = $cfg['module_srl'];
			$mod = $module_srl > 0 ? $oModuleModel->getModuleInfoByModuleSrl($module_srl) : null;
			$board_title = ($mod && isset($mod->browser_title) && $mod->browser_title !== '') ? $mod->browser_title : ('게시판 ' . $tab_index);
			$resolved_module_srl = 0;
			if ($mod && isset($mod->module) && $mod->module === 'board')
			{
				$resolved_module_srl = (int) $mod->module_srl;
				$query_args = new stdClass();
				$query_args->module_srl = $resolved_module_srl;
				$query_args->status = 'PUBLIC';
				$query_args->list_count = $list_count;
				$query_args->page = 1;
				$query_args->page_count = 1;

				$output = $oPoomahhiModel->getPopularDocumentsByBoards($query_args);
				$list = array();
				if ($output->toBool() && !empty($output->data))
				{
					$list = array_map(function($row) { return (object)(array)$row; }, array_values($output->data));
				}
			}
			else
			{
				$list = array();
				if ($module_srl > 0) $board_title = '게시판 ' . $tab_index;
			}

			$module_mid = ($mod && isset($mod->mid) && $mod->mid !== '') ? $mod->mid : '';

			$tabs[] = (object)array(
				'tab_index' => $tab_index,
				'board_title' => $board_title,
				'list' => $list,
				'module_srl' => $resolved_module_srl,
				'module_mid' => $module_mid
			);
		}

		$raw_tab = Context::get('tab');
		$current_tab = ($raw_tab !== null && $raw_tab !== '' && is_numeric($raw_tab)) ? (int) $raw_tab : 0;
		if ($current_tab < 1 || $current_tab > 3) $current_tab = 0;

		if ($current_tab === 0)
		{
			$page_mid = trim((string) Context::get('mid'));
			if ($page_mid !== '')
			{
				// 지역 품앗이(local_poomahhi) 화면에서는 지역 내돈내산(money2) 탭을 기본 선택
				$mid_for_tab = ($page_mid === 'local_poomahhi') ? 'money2' : $page_mid;
				foreach ($tabs as $tab)
				{
					if ($tab->module_mid !== '' && $tab->module_mid === $mid_for_tab)
					{
						$current_tab = (int) $tab->tab_index;
						break;
					}
				}
			}
		}
		if ($current_tab < 1 || $current_tab > 3) $current_tab = 1;

		$active_list = array();
		$active_board_title = '';
		if (isset($tabs[$current_tab - 1]))
		{
			$active_list = $tabs[$current_tab - 1]->list;
			$active_board_title = $tabs[$current_tab - 1]->board_title;
		}

		$skin_path = $this->widget_path . 'skins/' . (isset($args->skin) ? $args->skin : 'default') . '/';

		Context::set('tabs', $tabs);
		Context::set('current_tab', $current_tab);
		Context::set('active_list', $active_list);
		Context::set('active_board_title', $active_board_title);
		Context::set('widget_args', $args);
		Context::set('widget_page_mid', Context::get('mid'));

		$oTemplate = new Rhymix\Framework\Template($skin_path, 'index');
		return $oTemplate->compile();
	}
}
