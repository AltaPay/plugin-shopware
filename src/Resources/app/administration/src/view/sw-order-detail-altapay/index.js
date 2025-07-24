import template from './sw-order-detail-altapay.html.twig';

const { mapVuexGetters, mapVuexState } = Shopware.Component.getComponentHelper();
const { State } = Shopware;

Shopware.Component.register('sw-order-detail-altapay', {
    template,

    inject: ['altaPayService'],

    computed: {
        ...mapVuexGetters('swOrderDetail', [
            'isLoading',
        ]),
        order() {
            return Shopware.Store.get('swOrderDetail').order;
        }
    },

    metaInfo() {
        return {
            title: 'AltaPay'
        }
    },

    data() {
        return {
            transaction: null
        }
    },

    methods: {
        createdComponent() {
            State.commit('swOrderDetail/setLoading', ['order', true]);
            this.altaPayService.getPayments(this.order.id).then(response => {
                this.transaction = response.Body.Transactions.Transaction;
            }).finally(() => {
                State.commit('swOrderDetail/setLoading', ['order', false]);
            });
        },
        capture() {
            State.commit('swOrderDetail/setLoading', ['order', true]);
            this.altaPayService.capture(this.order.id).then(response => {
                this.transaction = response.Body.Transactions.Transaction;
                this.$emit('save-edits');
            }).finally(() => {
                State.commit('swOrderDetail/setLoading', ['order', false]);
            });
        },
        refund() {
            State.commit('swOrderDetail/setLoading', ['order', true]);
            this.altaPayService.refund(this.order.id).then(response => {
                this.transaction = response.Body.Transactions.Transaction;
                this.$emit('save-edits');
            }).finally(() => {
                State.commit('swOrderDetail/setLoading', ['order', false]);
            });
        },
        cancel() {
            State.commit('swOrderDetail/setLoading', ['order', true]);
            this.altaPayService.cancel(this.order.id).then(response => {
                this.transaction = response.Body.Transactions.Transaction;
                this.$emit('save-edits');
            }).finally(() => {
                State.commit('swOrderDetail/setLoading', ['order', false]);
            });
        }
    },

    created() {
        this.createdComponent();
    }
})
