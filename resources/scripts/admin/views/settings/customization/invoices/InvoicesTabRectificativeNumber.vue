<template>
  <NumberCustomizer
    type="invoice"
    :type-store="invoiceStore"
    default-series="R"
    setting-key="verifactu_rectificative_number_format"
    next-number-key="invoice_rectificative"
    title-key="settings.customization.invoices.rectificative_number_format"
    description-key="settings.customization.invoices.rectificative_number_format_description"
    preview-key="settings.customization.invoices.preview_rectificative_number"
    success-message-key="settings.customization.invoices.rectificative_number_settings_updated"
  />
</template>

<script setup>
import { useInvoiceStore } from '@/scripts/admin/stores/invoice'
import NumberCustomizer from '../NumberCustomizer.vue'

const invoiceStore = useInvoiceStore()
</script>
