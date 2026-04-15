import addressStub from '@/scripts/admin/stub/address.js'

export default function () {
  return {
    name: '',
    contact_name: '',
    tax_number: null,
    email: '',
    phone: null,
    password: '',
    confirm_password:'',
    currency_id: null,
    website: null,
    billing: { ...addressStub },
    shipping: { ...addressStub },
    customFields: [],
    fields: [],
    enable_portal: false,
  }
}
