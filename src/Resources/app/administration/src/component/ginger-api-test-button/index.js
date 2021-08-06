const { Component, Mixin } = Shopware;
import template from './ginger-api-test-button.html.twig';

Component.register('ginger-api-test-button', {
    template,

    props: ['label'],
    inject: ['gingerApiTest'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
        };
    },

    computed: {
        pluginConfig() {
            let $parent = this.$parent;

            while ($parent.actualConfigData === undefined) {
                $parent = $parent.$parent;
            }

            return $parent.actualConfigData.null;
        }
    },

    methods: {
        saveFinish() {
            this.isSaveSuccessful = false;
        },

        check() {
            this.isLoading = true;
            this.gingerApiTest.check(this.pluginConfig).then((res) => {
                if (res.success) {
                    this.isSaveSuccessful = true;
                    this.createNotificationSuccess({
                        title: this.$tc('ginger-api-test-button.title'),
                        message: this.$tc('ginger-api-test-button.success')
                    });
                } else {
                    this.createNotificationError({
                        title: this.$tc('ginger-api-test-button.title'),
                        message: this.$tc('ginger-api-test-button.error')
                    });
                }

                this.isLoading = false;
            });
        }
    }
})
