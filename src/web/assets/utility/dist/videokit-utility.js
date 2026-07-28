/**
 * VideoKit utility: clear or refresh the metadata cache.
 */
(function ($) {
    'use strict';

    if (typeof Craft === 'undefined') {
        return;
    }

    $(function () {
        var $utility = $('#videokit-utility');

        if (!$utility.length) {
            return;
        }

        var $provider = $utility.find('#videokit-provider');
        var $spinner = $utility.find('#videokit-spinner');
        var $result = $utility.find('#videokit-result');
        var $buttons = $utility.find('#videokit-clear, #videokit-refresh');

        function run(action) {
            $buttons.addClass('disabled').attr('disabled', 'disabled');
            $spinner.removeClass('hidden');
            $result.text('');

            Craft.sendActionRequest('POST', 'videokit/cache/' + action, {
                data: {provider: $provider.val() || ''},
            })
                .then(function (response) {
                    $result.text(response.data.message || '');
                })
                .catch(function (error) {
                    var data = error && error.response && error.response.data;
                    $result.text(
                        (data && (data.error || data.message))
                        || Craft.t('videokit', 'The request failed. Check the Craft logs for details.')
                    );
                })
                .finally(function () {
                    $buttons.removeClass('disabled').removeAttr('disabled');
                    $spinner.addClass('hidden');
                });
        }

        $utility.find('#videokit-clear').on('click', function () {
            run('clear');
        });

        $utility.find('#videokit-refresh').on('click', function () {
            run('refresh');
        });
    });
})(jQuery);
