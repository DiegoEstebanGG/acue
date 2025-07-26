jQuery(document).ready(function ($) {
    $('#learning-goal-filter').on('change', function () {
        const selectedGoal = $(this).val();

        $.post(bbCustomAjax.ajaxurl, {
            action: 'bb_filter_members',
            goal: selectedGoal,
        }, function (response) {
            $('#learning-directory-container').html(response);
        });
    });
});