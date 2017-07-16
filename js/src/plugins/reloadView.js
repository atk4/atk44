import atkPlugin from 'plugins/atkPlugin';

export default class reloadView extends atkPlugin {

    main(options) {
        this.settings = options;
        const spinner = this.$el.spinner({
            'loaderText': '',
            'active': true,
            'inline': true,
            'centered': true,
            'replace': false});

        if(this.settings.uri) {
            this.$el.api({
                on: 'now',
                url: this.settings.uri,
                data: this.settings.uri_options,
                method: 'GET',
                obj: this.$el,
                onComplete: function(response, content){
                    content.spinner('remove');
                }
            });
        }
    }
}

reloadView.DEFAULTS = {
    uri: null,
    uri_options: {},
};
