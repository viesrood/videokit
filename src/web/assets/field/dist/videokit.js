/**
 * VideoKit control panel field.
 *
 * Two Garnish classes: the field input itself, and the browse modal it opens.
 * Instances are created from the field's inputHtml() through
 * registerJsWithVars(), which is also what makes this work inside Matrix
 * blocks, slideouts and card editors: Craft returns the registered JS with the
 * Ajax payload and re-runs it there.
 *
 * No dependencies beyond Craft's own CP bundle, and no build step.
 */
(function ($) {
    'use strict';

    if (typeof Craft === 'undefined' || typeof Garnish === 'undefined') {
        return;
    }

    Craft.VideoKit = Craft.VideoKit || {};

    function t(message, params) {
        return Craft.t('videokit', message, params);
    }

    /**
     * Unpacks Craft's JSON failure shape into something showable.
     */
    function errorMessage(error) {
        var data = error && error.response && error.response.data;

        if (data && data.error) {
            return data.error;
        }

        if (data && data.message) {
            return data.message;
        }

        return t('Could not reach the video service.');
    }

    function post(action, data) {
        return Craft.sendActionRequest('POST', 'videokit/videos/' + action, {data: data});
    }

    /**
     * Renders one video card, shared by the preview and the modal grid.
     */
    function videoCard(video, extraClass) {
        var $card = $('<div class="vk-card' + (extraClass ? ' ' + extraClass : '') + '"/>');
        var $thumb = $('<div class="vk-card__thumb"/>').appendTo($card);

        if (video.thumbnail) {
            $('<img alt="" loading="lazy"/>').attr('src', video.thumbnail).appendTo($thumb);
        }

        if (video.duration) {
            $('<span class="vk-card__duration"/>').text(video.duration).appendTo($thumb);
        }

        var $body = $('<div class="vk-card__body"/>').appendTo($card);

        $('<span class="vk-card__title"/>').text(video.title || video.url || '').appendTo($body);

        var meta = [];

        if (video.providerName) {
            meta.push(video.providerName);
        }

        if (video.authorName) {
            meta.push(video.authorName);
        }

        $('<span class="vk-card__meta"/>').text(meta.join(' - ')).appendTo($body);

        if (!video.hasMetadata) {
            $('<span class="vk-card__warning"/>')
                .text(t('This video has no stored details yet.'))
                .appendTo($body);
        }

        return $card;
    }

    Craft.VideoKit.Input = Garnish.Base.extend({
        $container: null,
        $input: null,
        $preview: null,
        $error: null,
        $spinner: null,
        settings: null,
        video: null,
        lookupTimeout: null,

        init: function (containerId, settings) {
            this.$container = $('#' + containerId);

            if (!this.$container.length || this.$container.data('videokitInit')) {
                return;
            }

            this.$container.data('videokitInit', true);

            this.settings = settings || {};
            this.$input = this.$container.find('.vk-field__input');
            this.$preview = this.$container.find('.vk-field__preview');
            this.$error = this.$container.find('.vk-field__error');
            this.$spinner = this.$container.find('.vk-field__spinner');

            this.setVideo(this.settings.value || null, false);

            this.addListener(this.$input, 'input', 'onInput');
            this.addListener(this.$input, 'change', 'lookup');
            this.addListener(this.$container.find('.vk-field__browse'), 'click', 'openBrowser');
        },

        onInput: function () {
            if (this.lookupTimeout) {
                clearTimeout(this.lookupTimeout);
            }

            this.lookupTimeout = setTimeout(this.lookup.bind(this), 400);
        },

        lookup: function (refresh) {
            if (this.lookupTimeout) {
                clearTimeout(this.lookupTimeout);
                this.lookupTimeout = null;
            }

            var url = $.trim(this.$input.val());

            if (!url) {
                this.setVideo(null);
                this.showError(null);

                return;
            }

            if (this.video && this.video.url === url && refresh !== true) {
                return;
            }

            this.setBusy(true);

            post('get-video', {url: url, refresh: refresh === true ? 1 : 0})
                .then(function (response) {
                    this.showError(null);
                    this.setVideo(response.data.video);
                }.bind(this))
                .catch(function (error) {
                    this.showError(errorMessage(error));
                    this.setVideo(null);
                }.bind(this))
                .finally(function () {
                    this.setBusy(false);
                }.bind(this));
        },

        openBrowser: function () {
            new Craft.VideoKit.BrowseModal(this.settings, function (video) {
                this.showError(null);
                this.setVideo(video);
            }.bind(this));
        },

        /**
         * The hidden inputs are rebuilt from scratch on every change, so the
         * posted value always matches what is on screen.
         */
        setVideo: function (video, updateInput) {
            this.video = video && video.provider && video.videoId ? video : null;

            this.$container.find('.vk-field__value').remove();

            if (this.video) {
                var name = this.settings.name;
                var values = {
                    url: this.video.url || '',
                    provider: this.video.provider,
                    videoId: this.video.videoId,
                    hash: this.video.hash || '',
                };

                for (var key in values) {
                    if (Object.prototype.hasOwnProperty.call(values, key)) {
                        $('<input type="hidden" class="vk-field__value"/>')
                            .attr('name', name + '[' + key + ']')
                            .val(values[key])
                            .appendTo(this.$container);
                    }
                }

                if (updateInput !== false && this.video.url) {
                    this.$input.val(this.video.url);
                }
            }

            this.renderPreview();
        },

        renderPreview: function () {
            if (!this.settings.showPreview) {
                return;
            }

            this.$preview.empty();

            if (!this.video) {
                this.$preview.addClass('hidden');

                return;
            }

            this.$preview.removeClass('hidden');

            var $card = videoCard(this.video).appendTo(this.$preview);
            var $actions = $('<div class="vk-card__actions"/>').appendTo($card);

            var $refresh = $('<button type="button" class="btn small"/>')
                .text(t('Refresh'))
                .appendTo($actions);

            var $remove = $('<button type="button" class="btn small"/>')
                .attr('aria-label', t('Remove video'))
                .text(t('Clear'))
                .appendTo($actions);

            this.addListener($refresh, 'click', function () {
                this.lookup(true);
            });

            this.addListener($remove, 'click', function () {
                this.$input.val('');
                this.setVideo(null);
                this.showError(null);
            });
        },

        showError: function (message) {
            if (!message) {
                this.$error.addClass('hidden').text('');

                return;
            }

            this.$error.removeClass('hidden').text(message);
        },

        setBusy: function (busy) {
            this.$spinner.toggleClass('hidden', !busy);
        },
    });

    Craft.VideoKit.BrowseModal = Garnish.Modal.extend({
        settings: null,
        onSelect: null,
        provider: null,
        collection: null,
        nextPage: null,
        query: '',
        loading: false,
        selected: null,
        searchTimeout: null,

        $body: null,
        $results: null,
        $sidebar: null,
        $search: null,
        $more: null,
        $selectBtn: null,
        $spinner: null,
        $message: null,

        init: function (settings, onSelect) {
            this.settings = settings;
            this.onSelect = onSelect;

            var $container = $('<div class="modal elementselectormodal vk-modal"/>').appendTo(Garnish.$bod);

            this.base($container, {resizable: true});

            this.buildChrome($container);

            var providers = this.settings.providers || [];

            if (providers.length) {
                this.setProvider(providers[0].handle);
            }
        },

        buildChrome: function ($container) {
            var $header = $('<header class="header"/>').appendTo($container);

            $('<h1/>').text(t('Choose a video')).appendTo($header);

            var providers = this.settings.providers || [];

            if (providers.length > 1) {
                var $tabs = $('<div class="vk-modal__providers btngroup"/>').appendTo($header);

                providers.forEach(function (provider) {
                    var $tab = $('<button type="button" class="btn"/>')
                        .attr('data-provider', provider.handle)
                        .text(provider.name)
                        .appendTo($tabs);

                    this.addListener($tab, 'click', function () {
                        this.setProvider(provider.handle);
                    });
                }, this);
            }

            this.$body = $('<div class="body vk-modal__body"/>').appendTo($container);
            this.$sidebar = $('<div class="vk-modal__sidebar"/>').appendTo(this.$body);

            var $main = $('<div class="vk-modal__main"/>').appendTo(this.$body);
            var $searchRow = $('<div class="vk-modal__search"/>').appendTo($main);

            this.$search = $('<input type="text" class="text fullwidth"/>')
                .attr('placeholder', t('Search videos'))
                .attr('aria-label', t('Search'))
                .appendTo($searchRow);

            this.$message = $('<p class="vk-modal__message light"/>').appendTo($main);
            this.$results = $('<div class="vk-modal__results"/>').appendTo($main);
            this.$more = $('<button type="button" class="btn vk-modal__more hidden"/>')
                .text(t('Load more'))
                .appendTo($main);

            var $footer = $('<div class="footer"/>').appendTo($container);

            this.$spinner = $('<div class="spinner hidden"/>').appendTo($footer);

            var $buttons = $('<div class="buttons right"/>').appendTo($footer);
            var $cancel = $('<button type="button" class="btn"/>').text(t('Cancel')).appendTo($buttons);

            this.$selectBtn = $('<button type="button" class="btn submit disabled"/>')
                .text(t('Select'))
                .appendTo($buttons);

            this.addListener(this.$search, 'input', 'onSearchInput');
            this.addListener(this.$more, 'click', 'loadMore');
            this.addListener($cancel, 'click', 'hide');
            this.addListener(this.$selectBtn, 'click', 'confirmSelection');
        },

        setProvider: function (handle) {
            this.provider = (this.settings.providers || []).filter(function (provider) {
                return provider.handle === handle;
            })[0] || null;

            if (!this.provider) {
                return;
            }

            this.$body.find('.vk-modal__providers .btn').removeClass('active');
            this.$body.closest('.vk-modal').find('[data-provider="' + handle + '"]').addClass('active');

            this.$search.closest('.vk-modal__search').toggleClass('hidden', !this.provider.supportsSearch);
            this.$search.val('');
            this.query = '';
            this.collection = null;
            this.clearResults();
            this.loadCollections();
        },

        loadCollections: function () {
            this.$sidebar.empty();

            if (!this.provider.supportsCollections) {
                this.setMessage(t('Search videos'));

                return;
            }

            this.setBusy(true);

            post('get-collections', {provider: this.provider.handle})
                .then(function (response) {
                    var collections = response.data.collections || [];

                    collections.forEach(function (collection, index) {
                        var $item = $('<button type="button" class="vk-modal__collection"/>')
                            .text(collection.name + (collection.count !== null ? ' (' + collection.count + ')' : ''))
                            .appendTo(this.$sidebar);

                        this.addListener($item, 'click', function () {
                            this.$sidebar.find('.vk-modal__collection').removeClass('sel');
                            $item.addClass('sel');
                            this.setCollection(collection);
                        });

                        if (index === 0) {
                            $item.addClass('sel');
                            this.setCollection(collection);
                        }
                    }, this);

                    if (!collections.length) {
                        this.setMessage(t('Nothing to show yet.'));
                    }
                }.bind(this))
                .catch(function (error) {
                    this.setMessage(errorMessage(error));
                }.bind(this))
                .finally(function () {
                    this.setBusy(false);
                }.bind(this));
        },

        setCollection: function (collection) {
            this.collection = collection;
            this.query = '';
            this.$search.val('');
            this.clearResults();
            this.fetch();
        },

        onSearchInput: function () {
            if (this.searchTimeout) {
                clearTimeout(this.searchTimeout);
            }

            // Debounced, and only from three characters: YouTube charges 100
            // quota units per search out of 10,000 a day.
            this.searchTimeout = setTimeout(function () {
                var query = $.trim(this.$search.val());

                if (query === this.query) {
                    return;
                }

                this.query = query;
                this.clearResults();

                if (!query) {
                    if (this.collection) {
                        this.fetch();
                    }

                    return;
                }

                if (query.length < 3) {
                    this.setMessage(t('Enter at least three characters.'));

                    return;
                }

                this.fetch();
            }.bind(this), 400);
        },

        fetch: function () {
            if (this.loading) {
                return;
            }

            var searching = this.query.length >= 3;

            if (!searching && !this.collection) {
                return;
            }

            var action = searching ? 'search' : 'get-collection-videos';
            var data = {provider: this.provider.handle};

            if (searching) {
                data.q = this.query;
            } else {
                data.collectionType = this.collection.type;
                data.collectionId = this.collection.id;
            }

            if (this.nextPage) {
                data.page = this.nextPage;
            }

            this.loading = true;
            this.setBusy(true);
            this.setMessage(this.$results.children().length ? '' : t('Loading...'));

            post(action, data)
                .then(function (response) {
                    this.appendVideos(response.data.videos || []);
                    this.nextPage = response.data.nextPage || null;
                    this.$more.toggleClass('hidden', !this.nextPage);

                    if (!this.$results.children().length) {
                        this.setMessage(t('No videos found.'));
                    } else {
                        this.setMessage('');
                    }
                }.bind(this))
                .catch(function (error) {
                    this.setMessage(errorMessage(error));
                }.bind(this))
                .finally(function () {
                    this.loading = false;
                    this.setBusy(false);
                }.bind(this));
        },

        loadMore: function () {
            if (this.nextPage) {
                this.fetch();
            }
        },

        appendVideos: function (videos) {
            videos.forEach(function (video) {
                var $card = videoCard(video, 'vk-card--selectable').appendTo(this.$results);

                this.addListener($card, 'click', function () {
                    this.$results.find('.vk-card').removeClass('sel');
                    $card.addClass('sel');
                    this.selected = video;
                    this.$selectBtn.removeClass('disabled');
                });

                this.addListener($card, 'dblclick', function () {
                    this.selected = video;
                    this.confirmSelection();
                });
            }, this);
        },

        clearResults: function () {
            this.$results.empty();
            this.nextPage = null;
            this.selected = null;
            this.$more.addClass('hidden');
            this.$selectBtn.addClass('disabled');
            this.setMessage('');
        },

        confirmSelection: function () {
            if (!this.selected) {
                return;
            }

            this.onSelect(this.selected);
            this.hide();
        },

        setMessage: function (message) {
            this.$message.text(message || '').toggleClass('hidden', !message);
        },

        setBusy: function (busy) {
            this.$spinner.toggleClass('hidden', !busy);
        },
    });
})(jQuery);
