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
            refundAmount: 0,
            isLoadingCapture: false,
            isLoadingRefund: false,
            showCaptureModal: false,
            showRefundModal: false,
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
            this.isLoadingCapture = true;
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
                    this.isLoadingCapture = false;
                    this.closeCaptureModal();
                    State.commit('swOrderDetail/setLoading', ['order', false]);
                }, 2000);
            });
        },
        refund() {
            this.isLoadingRefund = true;
            State.commit('swOrderDetail/setLoading', ['order', true]);
            this.altaPayService.refund(this.orderId, this.refundAmount).then(response => {
                if (response.Body.Result === 'Error') {
                    this.errorMessage = response.Body.MerchantErrorMessage;
                    return;
                } else {
                    this.successMessage = 'Refunded successfully.';
                }
                this.transaction = response.Body.Transactions.Transaction;
                this.$emit('save-edits');
            }).finally(() => {
                setTimeout(() => {
                    this.isLoadingRefund = false;
                    this.closeRefundModal();
                    State.commit('swOrderDetail/setLoading', ['order', false]);
                }, 2000);
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
        },
        openRefundModal() {
            this.refundAmount = this.transaction.CapturedAmount - this.transaction.RefundedAmount;
            this.showRefundModal = true;
        },
        closeRefundModal() {
            this.showRefundModal = false;
            this.errorMessage = '';
            this.successMessage = '';
        }
    },

    created() {
        this.createdComponent();
    }
})
