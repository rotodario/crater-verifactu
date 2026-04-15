import Guid from 'guid'
import invoiceItemStub from './invoice-item'
import taxStub from './tax'

export default function () {
  return {
    id: null,
    invoice_number: '',
    customer: null,
    customer_id: null,
    template_name: null,
    invoice_date: '',
    due_date: '',
    notes: '',
    discount: 0,
    discount_type: 'fixed',
    discount_val: 0,
    reference_number: null,
    tax: 0,
    sub_total: 0,
    total: 0,
    tax_per_item: null,
    sales_tax_type: null,
    sales_tax_address_type: null,
    discount_per_item: null,
    status: 'DRAFT',
    paid_status: 'UNPAID',
    allow_edit: true,
    fiscal_status: 'NOT_ISSUED',
    fiscal_issued_at: null,
    fiscal_locked_at: null,
    invoice_kind: 'STANDARD',
    original_invoice_id: null,
    rectification_type: null,
    rectification_reason: null,
    verifactu_qr_string: null,
    taxes: [],
    items: [
      {
        ...invoiceItemStub,
        id: Guid.raw(),
        taxes: [{ ...taxStub, id: Guid.raw() }],
      },
    ],
    customFields: [],
    fields: [],
    selectedNote: null,
    selectedCurrency: '',
  }
}
