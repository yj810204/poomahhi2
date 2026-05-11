/**
 * @file   modules/pointhistory/tpl/js/pointhistory_upgrade.js
 * @author CONORY (https://xe.conory.com)
 * @brief  pointhistory 모듈의 관리자용 javascript
 **/

jQuery(function($){
	var processing_speed = 100;
	
	var upgrading = function(){
		$.exec_json('pointhistory.procPointhistoryAdminUpgrade', {type: 'start', max_count: processing_speed},
			function(data) {
				if(data.percent == 100)
				{
					alert(data.message);
					location.reload();
				}
				else
				{
					$('#totalCount').text(data.total);
					$('#completeCount').text(data.current);
					$('#progressBar').width(data.percent + '%');
					$('#progressPercent').html(data.percent + "%");
					
					setTimeout(upgrading, data.timeout);
				}
			},
			// failure
			function(response) {
				$('#step2').hide();
				$('#step1').show();
				$('#upgrade_start, #use_new').removeAttr('disabled');
			}
		);
	};
	
	$('#upgrade_start').click(function(event){
		$('#upgrade_start, #use_new').attr('disabled', 'disabled');
		event.preventDefault();
		
		if(!confirm(lang_confirm_upgrade_start))
		{
			$('#upgrade_start, #use_new').removeAttr('disabled');
			return false;
		}
		
		$('#step1').hide();
		$('#step2').show();
		
		processing_speed = $('input[name="processing_speed"]:checked').val();
		
		upgrading();
	});
	
	$('#use_new').click(function(event){
		$('#upgrade_start, #use_new').attr('disabled', 'disabled');
		event.preventDefault();
		
		if(!confirm(lang_confirm_upgrade_use_new))
		{
			$('#upgrade_start, #use_new').removeAttr('disabled');
			return false;
		}
		
		// 서버 작업
		$.exec_json('pointhistory.procPointhistoryAdminUpgrade', {type: 'new'},
			function(data) {
				alert(data.message);
				location.reload();
			},
			// failure
			function(response) {
				$('#upgrade_start, #use_new').removeAttr('disabled');
			}
		);
	});
});