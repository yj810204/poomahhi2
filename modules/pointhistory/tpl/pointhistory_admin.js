/**
 * @file   modules/pointhistory/tpl/js/pointhistory_admin.js
 * @author CONORY (https://xe.conory.com)
 * @brief  pointhistory 모듈의 관리자용 javascript
 **/

/* 데이터 삭제 */
function deleteDate(date_srl)
{
    var fo_obj = xGetElementById('deleteForm');
	
	if(date_srl)
	{
		fo_obj.date_srl.value = date_srl;
	}
	fo_obj.submit();
	
	return false;
}
