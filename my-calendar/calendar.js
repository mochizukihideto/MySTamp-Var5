$(document).ready(function() {
    $('.calendar-stamp').hover(
        function(e) {
            var stamp = $(this).data('stamp');
            console.log('Hover stamp data:', stamp);  // デバッグ用
            
            var info = '<strong>' + (stamp.hobby || 'Unknown') + '</strong><br>' +
                       '開始日: ' + (stamp.start_date || 'N/A') + '<br>' +
                       '頻度: ' + (stamp.frequency_count || 'N/A') + '回/' + (stamp.frequency_type || 'N/A') + '<br>' +
                       '所要時間: ' + (stamp.duration || 'N/A') + '分<br>' +
                       '中間目標: ' + (stamp.intermediate_goal_count || 'N/A') + (stamp.intermediate_goal_type || 'N/A') + '<br>' +
                       '継続期間: ' + (stamp.duration_period || 'N/A') + '<br>' +
                       '合計回数: ' + (stamp.total_count !== undefined ? stamp.total_count : 'N/A') + '回<br>' +
                       '合計時間: ' + (stamp.total_hours !== undefined ? stamp.total_hours + '時間' + stamp.total_minutes + '分' : 'N/A');
            
            $('#stamp-info').html(info).css({
                top: e.pageY + 10,
                left: e.pageX + 10,
                display: 'block'
            });
        },
        function() {
            $('#stamp-info').hide();
        }
    );

    $(document).mousemove(function(e) {
        if ($('#stamp-info').is(':visible')) {
            $('#stamp-info').css({
                top: e.pageY + 10,
                left: e.pageX + 10
            });
        }
    });
});