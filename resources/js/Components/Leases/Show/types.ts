export interface DepositPayment {
    id: number;
    amount: number;
    paid_at: string;
    payment_method: string;
    payment_method_label: string;
    reference: string;
    notes: string | null;
    recorded_by: string;
    is_reversed: boolean;
    reversal_reason: string | null;
    reversed_at: string | null;
    reversed_by: string | null;
}

export interface DepositDeduction {
    id: number;
    amount: number;
    category: string;
    category_label: string;
    description: string;
    notes?: string | null;
    attachment_url?: string | null;
    status: string;
    deducted_at: string;
    recorded_by: string;
}

export interface DepositRefund {
    id: number;
    amount: number;
    refunded_at: string;
    payment_method: string;
    payment_method_label: string;
    reference: string;
    notes: string | null;
    recorded_by: string;
    is_reversed: boolean;
    reversal_reason: string | null;
}

export interface RentChargeItem {
    id: number;
    period: string;
    due_date: string;
    amount: number;
    paid_amount: number;
    outstanding_balance: number;
    status: string;
}

export interface ApartmentGenderInfo {
    status: 'male' | 'female' | 'mixed' | 'unspecified';
    label: string;
    short_label: string;
    description: string;
    badge_class: string;
    male_count: number;
    female_count: number;
    unspecified_count: number;
    total_active_renters: number;
}

export interface LeaseData {
    id: number;
    contract_number: string;
    starts_on: string;
    ends_on: string;
    raw_starts_on: string;
    raw_ends_on: string;
    rent_amount: number;
    billing_interval: string;
    status: string;
    status_label: string;
    is_past_end_date?: boolean;
    is_expiring_soon?: boolean;
    notes: string | null;
    contract_image_url?: string | null;
    has_contract_image?: boolean;
    created_at: string;
}

export interface UnitData {
    id: number;
    name: string;
    code: string;
    unit_type: string;
    unit_type_label: string;
    property_id: number;
    property_name: string;
    property_address: string | null;
    apartment_gender?: ApartmentGenderInfo | null;
}

export interface RenterData {
    id: number;
    name: string;
    phone: string;
    national_id: string | null;
    email: string | null;
    gender?: 'male' | 'female' | null;
    gender_label?: string | null;
    status?: 'green' | 'yellow' | 'red' | string | null;
    status_details?: {
        value?: string;
        label?: string;
        description?: string;
        color?: string;
        badge_class?: string;
        dot_class?: string;
    } | null;
}

export interface SettlementData {
    id: number;
    status: string;
    settlement_type?: 'full_refund' | 'partial_refund' | 'no_refund' | null;
    settlement_type_label?: string | null;
    retention_reason?: string | null;
    original_deposit?: number | null;
    inspection_category: string;
    inspection_category_label: string;
    inspection_notes: string | null;
    total_received?: number;
    total_deductions?: number;
    total_refunded?: number;
    refundable_balance?: number;
    refund_status?: 'pending' | 'refunded' | 'not_applicable';
    refund_status_label?: string;
    refund_method?: string | null;
    refund_payment_date?: string | null;
    refund_reference?: string | null;
    settled_at: string | null;
    settled_by: string | null;
    notes: string | null;
}

export interface SecurityDepositActions {
    can_record_payment: boolean;
    can_start_settlement: boolean;
    can_record_deduction: boolean;
    can_record_refund: boolean;
    can_settle: boolean;
    can_record_pending_refund?: boolean;
    can_extend?: boolean;
    can_terminate?: boolean;
}

export interface SecurityDepositData {
    agreed_amount: number;
    total_received: number;
    outstanding_amount: number;
    total_deductions: number;
    total_refunded: number;
    refundable_balance: number;
    status: string;
    status_label: string;
    payments: DepositPayment[];
    deductions: DepositDeduction[];
    refunds: DepositRefund[];
    settlement: SettlementData | null;
    actions: SecurityDepositActions;
}

export type DepositLedgerKind = 'payment' | 'deduction' | 'refund';

export interface DepositLedgerEntry {
    key: string;
    kind: DepositLedgerKind;
    title: string;
    occurredAt: string;
    amount: number;
    method: string | null;
    reference: string | null;
    recordedBy: string;
    description: string | null;
    notes: string | null;
    attachmentUrl: string | null;
    isReversed: boolean;
    reversalReason: string | null;
    payment: DepositPayment | null;
    deduction: DepositDeduction | null;
}

export interface DeductionDraft {
    id?: number;
    category: string;
    amount: number;
    description: string;
    notes: string;
    attachment: File | null;
    attachment_url?: string | null;
}

export interface ActionCenterItem {
    id: string;
    priority: number;
    title: string;
    description: string;
    badge?: string;
    badgeTone?: 'rose' | 'amber' | 'teal' | 'slate';
    buttonLabel: string;
    buttonTone?: 'primary' | 'secondary' | 'danger';
    action: string;
    meta?: any;
}

export interface RentTotals {
    billed: number;
    paid: number;
    outstanding: number;
    overdue: number;
    overdueCount: number;
}

