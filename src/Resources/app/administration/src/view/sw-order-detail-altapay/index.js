import template from './sw-order-detail-altapay.html.twig';

const { mapGetters, mapState } = Shopware.Component.getComponentHelper();
const { State } = Shopware;

Shopware.Component.register('sw-order-detail-altapay', {
    template,

    inject: ['altaPayService'],

    computed: {
        ...mapState('swOrderDetail', [
            'order'
        ]),
        orderDetails() {
            try {
                const version = Shopware?.Context?.app?.config?.version ?? '';
                if (!version.startsWith('6.6')) {
                    return Shopware.Store.get('swOrderDetail').order;
                }
            } catch (e) {
                console.log('swOrderDetail store not found:', e);
            }
        },
        orderId() {
            return this.orderDetails?.id || this.order?.id || null;
        },
        transactionSource() {
            return this.orderDetails || this.order || null;
        }
    },

    metaInfo() {
        return {
            title: 'AltaPay'
        }
    },

    data() {
        return {
            transaction: null,
            captureAmount: 0,
            isLoadingCapture: false,
            showCaptureModal: false,
            errorMessage: '',
            successMessage: ''
        }
    },

    methods: {
        createdComponent() {
            State.commit('swOrderDetail/setLoading', ['order', true]);
            this.altaPayService.getPayments(this.orderId).then(response => {
                this.transaction = response.Body.Transactions.Transaction;
            }).finally(() => {
                State.commit('swOrderDetail/setLoading', ['order', false]);
            });
        },
        capture() {
            State.commit('swOrderDetail/setLoading', ['order', true]);
            this.altaPayService.capture(this.orderId, this.captureAmount).then(response => {
                if (response.Body.Result === 'Error') {
                    this.errorMessage = response.Body.MerchantErrorMessage;
                    return;
                } else {
                    this.successMessage = 'Captured successfully.';
                }
                this.transaction = response.Body.Transactions.Transaction;
                this.$emit('save-edits');
            }).finally(() => {
                setTimeout(() => {
                    this.closeCaptureModal();
                    State.commit('swOrderDetail/setLoading', ['order', false]);
                }, 1000);
            });
        },
        refund() {
            State.commit('swOrderDetail/setLoading', ['order', true]);
            this.altaPayService.refund(this.orderId).then(response => {
                this.transaction = response.Body.Transactions.Transaction;
                this.$emit('save-edits');
            }).finally(() => {
                State.commit('swOrderDetail/setLoading', ['order', false]);
            });
        },
        cancel() {
            State.commit('swOrderDetail/setLoading', ['order', true]);
            this.altaPayService.cancel(this.orderId).then(response => {
                this.transaction = response.Body.Transactions.Transaction;
                this.$emit('save-edits');
            }).finally(() => {
                State.commit('swOrderDetail/setLoading', ['order', false]);
            });
        },
        openCaptureModal() {
            this.captureAmount = this.transaction.ReservedAmount - this.transaction.CapturedAmount;
            this.showCaptureModal = true;
        },

        closeCaptureModal() {
            this.showCaptureModal = false;
            this.errorMessage = '';
            this.successMessage = '';
        }
    },

    created() {
        this.createdComponent();
    }
})
