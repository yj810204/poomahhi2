<?php
/**
 * @class wp_kakao_noti
 * @brief SOLAPI 카카오 알림톡 모듈
 */
class wp_kakao_noti extends ModuleObject
{
	function moduleInstall()
	{
		$oDB = DB::getInstance();
		if(!$oDB->isTableExists('wp_kakao_noti_log'))
		{
			$oDB->createTableByXmlFile($this->module_path . 'schemas/wp_kakao_noti_log.xml');
		}

		return new BaseObject();
	}

	function checkUpdate()
	{
		$oDB = DB::getInstance();
		if(!$oDB->isTableExists('wp_kakao_noti_log'))
		{
			return true;
		}

		return false;
	}

	function moduleUpdate()
	{
		$oDB = DB::getInstance();
		if(!$oDB->isTableExists('wp_kakao_noti_log'))
		{
			$oDB->createTableByXmlFile($this->module_path . 'schemas/wp_kakao_noti_log.xml');
		}

		return new BaseObject();
	}

	function recompileCache()
	{
	}

	function moduleUninstall()
	{
		return new BaseObject();
	}

	/**
	 * 현재 요청의 mid (관리자 폼·URL용). 미설정 시 모듈 폴더명과 동일한 기본값.
	 */
	public static function wknCurrentMid()
	{
		$mid = trim((string)Context::get('mid'));

		return $mid !== '' ? $mid : 'wp_kakao_noti';
	}
}
