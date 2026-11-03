import type {
    ActionCenterItem,
    LeaseData,
    RentChargeItem,
    RentTotals,
    SecurityDepositData,
} from './types';

const millisecondsPerDay = 86_400_000;

export const parseIsoDate = (isoDate: string): Date => {
    const parts = isoDate.split('-');
    const year = Number(parts[0] || 2026);
    const month = Number(parts[1] || 1);
    const day = Number(parts[2] || 1);
    return new Date(year, month - 1, day);
};

export const parseDisplayDate = (displayDate: string): Date => {
    const [datePart = ''] = displayDate.split(' ');
    const parts = datePart.split('/');
    const day = Number(parts[0] || 1);
    const month = Number(parts[1] || 1);
    const year = Number(parts[2] || 2026);
    return new Date(year, month - 1, day);
};

export const formatMoney = (amount: number): string => {
    return Number(amount || 0).toLocaleString();
};

export const arabicCount = (
    count: number,
    forms: { one: string; two: string; few: string; many: string }
): string => {
    const abs = Math.abs(count);
    if (abs === 1) return forms.one;
    if (abs === 2) return forms.two;
    if (abs >= 3 && abs <= 10) return `${abs} ${forms.few}`;
    return `${abs} ${forms.many}`;
};

export const formatDuration = (days: number): string => {
    const absDays = Math.abs(days);
    if (absDays < 60) {
        return arabicCount(absDays, { one: 'يوم', two: 'يومان', few: 'أيام', many: 'يوماً' });
    }
    const months = Math.round(absDays / 30.44);
    if (months < 24) {
        return arabicCount(months, { one: 'شهر', two: 'شهران', few: 'أشهر', many: 'شهراً' });
    }
    const years = Math.round(absDays / 365.25);
    return arabicCount(years, { one: 'سنة', two: 'سنتان', few: 'سنوات', many: 'سنة' });
};

export const calculateTimeline = (
    rawStartsOn: string,
    rawEndsOn: string,
    isActive: boolean
) => {
    const startsOn = parseIsoDate(rawStartsOn).getTime();
    const endsOn = parseIsoDate(rawEndsOn).getTime();
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const todayTime = today.getTime();

    const totalDays = Math.max(1, Math.round((endsOn - startsOn) / millisecondsPerDay));
    const elapsedDays = Math.round((todayTime - startsOn) / millisecondsPerDay);
    const remainingDays = Math.round((endsOn - todayTime) / millisecondsPerDay);
    const elapsedPercent = Math.min(100, Math.max(0, Math.round((elapsedDays / totalDays) * 100)));

    let caption = `المدة الإجمالية ${formatDuration(totalDays)}`;
    if (isActive) {
        if (elapsedDays < 0) {
            caption = `يبدأ بعد ${formatDuration(-elapsedDays)}`;
        } else if (remainingDays < 0) {
            caption = `انتهت مدته منذ ${formatDuration(-remainingDays)}`;
        } else if (remainingDays === 0) {
            caption = 'ينتهي اليوم';
        } else {
            caption = `متبقٍ ${formatDuration(remainingDays)}`;
        }
    }

    return {
        totalDays,
        elapsedDays,
        remainingDays,
        elapsedPercent,
        caption,
    };
};

export const formatRelativeDays = (
    dueDateStr: string
): { text: string; isPast: boolean; isToday: boolean; absDays: number; daysDiff: number } => {
    const dueDate = dueDateStr.includes('-')
        ? parseIsoDate(dueDateStr)
        : parseDisplayDate(dueDateStr);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    dueDate.setHours(0, 0, 0, 0);

    const daysDiff = Math.round((dueDate.getTime() - today.getTime()) / millisecondsPerDay);
    const absDays = Math.abs(daysDiff);

    if (daysDiff === 0) {
        return { text: 'مستحقة اليوم', isPast: false, isToday: true, absDays: 0, daysDiff };
    }

    if (daysDiff > 0) {
        if (daysDiff === 1) {
            return { text: 'مستحقة غداً', isPast: false, isToday: false, absDays: 1, daysDiff };
        }
        if (daysDiff === 2) {
            return { text: 'مستحقة بعد يومين', isPast: false, isToday: false, absDays: 2, daysDiff };
        }
        return {
            text: `مستحقة بعد ${arabicCount(absDays, { one: 'يوم', two: 'يومان', few: 'أيام', many: 'يوماً' })}`,
            isPast: false,
            isToday: false,
            absDays,
            daysDiff,
        };
    }

    if (daysDiff === -1) {
        return { text: 'متأخرة منذ يوم', isPast: true, isToday: false, absDays: 1, daysDiff };
    }
    if (daysDiff === -2) {
        return { text: 'متأخرة منذ يومين', isPast: true, isToday: false, absDays: 2, daysDiff };
    }
    return {
        text: `متأخرة ${arabicCount(absDays, { one: 'يوم', two: 'يومان', few: 'أيام', many: 'يوماً' })}`,
        isPast: true,
        isToday: false,
        absDays,
        daysDiff,
    };
};

export const calculateRentTotals = (charges: RentChargeItem[]): RentTotals => {
    const totals = charges.reduce(
        (sums, charge) => {
            sums.billed += charge.amount;
            sums.paid += charge.paid_amount;
            sums.outstanding += charge.outstanding_balance;
            if (charge.status === 'overdue') {
                sums.overdue += charge.outstanding_balance;
                sums.overdueCount += 1;
            }
            return sums;
        },
        { billed: 0, paid: 0, outstanding: 0, overdue: 0, overdueCount: 0 }
    );

    const round2 = (num: number) => Math.round(num * 100) / 100;

    return {
        billed: round2(totals.billed),
        paid: round2(totals.paid),
        outstanding: round2(totals.outstanding),
        overdue: round2(totals.overdue),
        overdueCount: totals.overdueCount,
    };
};

export const deriveLeaseChip = (
    lease: LeaseData,
    remainingDays: number
): { status: string; label: string; tone: 'emerald' | 'amber' | 'rose' | 'slate' } => {
    if (lease.status === 'active') {
        if (lease.is_past_end_date || remainingDays < 0) {
            return {
                status: 'past_due',
                label: 'انتهت مدته (يتطلب إجراء)',
                tone: 'amber',
            };
        }
        if (lease.is_expiring_soon || remainingDays <= 30) {
            return {
                status: 'expiring',
                label: remainingDays === 0 ? 'سارٍ · ينتهي اليوم' : `سارٍ · ينتهي بعد ${formatDuration(remainingDays)}`,
                tone: 'amber',
            };
        }
        return {
            status: 'active',
            label: 'سارٍ',
            tone: 'emerald',
        };
    }

    if (lease.status === 'terminated') {
        return {
            status: 'terminated',
            label: 'منتهي ومسوّى',
            tone: 'rose',
        };
    }

    if (lease.status === 'expired') {
        return {
            status: 'expired',
            label: 'منتهي',
            tone: 'slate',
        };
    }

    if (lease.status === 'cancelled') {
        return {
            status: 'cancelled',
            label: 'ملغي',
            tone: 'rose',
        };
    }

    return {
        status: lease.status,
        label: lease.status_label || lease.status,
        tone: 'slate',
    };
};

export const deriveSmartPrimaryButton = (
    lease: LeaseData,
    rentTotals: RentTotals,
    securityDeposit: SecurityDepositData,
    remainingDays: number
): { key: string; label: string; action: string } => {
    const hasUnpaidRent = rentTotals.outstanding > 0;
    const isLeaseActive = lease.status === 'active';

    if (hasUnpaidRent && isLeaseActive) {
        return {
            key: 'record-rent-payment',
            label: 'تسجيل دفعة إيجار',
            action: 'record-rent-payment',
        };
    }

    if (isLeaseActive && (lease.is_past_end_date || remainingDays <= 30)) {
        return {
            key: 'extend-lease',
            label: 'تجديد العقد',
            action: 'extend-lease',
        };
    }

    if (!isLeaseActive && securityDeposit.actions.can_settle) {
        return {
            key: 'finalize-settlement',
            label: 'اعتماد التسوية',
            action: 'finalize-settlement',
        };
    }

    if (securityDeposit.actions.can_record_pending_refund) {
        return {
            key: 'pending-refund',
            label: 'صرف التأمين للمستأجر',
            action: 'pending-refund',
        };
    }

    if (isLeaseActive) {
        return {
            key: 'record-rent-payment',
            label: 'تسجيل دفعة إيجار',
            action: 'record-rent-payment',
        };
    }

    return {
        key: 'all-leases',
        label: 'كل العقود',
        action: 'all-leases',
    };
};

const resolveOverdueItem = (
    rentTotals: RentTotals,
    isLeaseActive: boolean,
    currencySymbol: string
): ActionCenterItem | null => {
    if (!isLeaseActive || rentTotals.overdue <= 0) {
        return null;
    }

    const countText = arabicCount(rentTotals.overdueCount, {
        one: 'فاتورة متأخرة',
        two: 'فاتورتان متأخرتان',
        few: 'فواتير متأخرة',
        many: 'فاتورة متأخرة',
    });

    return {
        id: 'overdue-rent',
        priority: 1,
        title: `متأخرات إيجار: ${formatMoney(rentTotals.overdue)} ${currencySymbol}`,
        description: `يوجد ${countText} تجاوزت موعد الاستحقاق المطلوب سداده.`,
        badge: 'متأخر',
        badgeTone: 'rose',
        buttonLabel: 'تسجيل دفعة',
        buttonTone: 'primary',
        action: 'record-rent-payment',
    };
};

const resolveUpcomingChargeItem = (
    rentCharges: RentChargeItem[],
    isLeaseActive: boolean,
    currencySymbol: string
): ActionCenterItem | null => {
    if (!isLeaseActive) {
        return null;
    }

    const upcomingCharge = rentCharges.find((charge) => {
        if (charge.outstanding_balance <= 0) return false;
        const rel = formatRelativeDays(charge.due_date);
        return !rel.isPast && rel.daysDiff <= 14;
    });

    if (!upcomingCharge) {
        return null;
    }

    const rel = formatRelativeDays(upcomingCharge.due_date);
    return {
        id: `upcoming-charge-${upcomingCharge.id}`,
        priority: 2,
        title: `فاتورة إيجار ${rel.text}`,
        description: `قيمة الفاتورة ${formatMoney(upcomingCharge.outstanding_balance)} ${currencySymbol}، موعد الاستحقاق: ${upcomingCharge.due_date}.`,
        badge: 'قادمة',
        badgeTone: 'amber',
        buttonLabel: 'تسجيل دفعة',
        buttonTone: 'secondary',
        action: 'record-charge-payment',
        meta: { charge: upcomingCharge },
    };
};

const resolveLeaseExpiryItem = (
    lease: LeaseData,
    remainingDays: number,
    isLeaseActive: boolean
): ActionCenterItem | null => {
    if (!isLeaseActive) {
        return null;
    }

    if (lease.is_past_end_date || remainingDays < 0) {
        return {
            id: 'lease-expired',
            priority: 3,
            title: `انتهت مدة العقد في ${lease.ends_on} ولم يُغلق بعد`,
            description: 'تبقى الوحدة مشغولة حتى تجدد العقد أو تنهيه وتسوي التأمين.',
            badge: 'انتهى موعده',
            badgeTone: 'amber',
            buttonLabel: 'تجديد العقد',
            buttonTone: 'secondary',
            action: 'extend-lease',
        };
    }

    if (lease.is_expiring_soon || remainingDays <= 30) {
        return {
            id: 'lease-expiring',
            priority: 3,
            title: `العقد ينتهي بعد ${formatDuration(remainingDays)} (${lease.ends_on})`,
            description: 'تواصل مع المستأجر لبحث تجديد العقد أو البدء في ترتيبات إخلاء الوحدة.',
            badge: 'قرب الانتهاء',
            badgeTone: 'amber',
            buttonLabel: 'تجديد العقد',
            buttonTone: 'secondary',
            action: 'extend-lease',
        };
    }

    return null;
};

const resolveDepositPendingRefundItem = (
    securityDeposit: SecurityDepositData,
    currencySymbol: string
): ActionCenterItem | null => {
    if (!securityDeposit.actions.can_record_pending_refund) {
        return null;
    }

    return {
        id: 'deposit-pending-refund',
        priority: 4,
        title: `اعتُمد رد ${formatMoney(securityDeposit.refundable_balance)} ${currencySymbol} للمستأجر ولم يُصرف بعد`,
        description: 'التسوية معتمدة وبانتظار توثيق التحويل أو الصرف الفعلي للمستأجر.',
        badge: 'بانتظار الصرف',
        badgeTone: 'amber',
        buttonLabel: 'تسجيل صرف المبلغ',
        buttonTone: 'primary',
        action: 'pending-refund',
    };
};

const resolveDepositReviewItem = (
    securityDeposit: SecurityDepositData
): ActionCenterItem | null => {
    if (securityDeposit.status !== 'under_review' || !securityDeposit.actions.can_settle) {
        return null;
    }

    return {
        id: 'deposit-under-review',
        priority: 5,
        title: 'تسوية التأمين مفتوحة وبانتظار المراجعة والاعتماد',
        description: 'راجع بنود الخصومات واعتمد التسوية لإغلاق حساب التأمين ورد المستحق.',
        badge: 'قيد التسوية',
        badgeTone: 'teal',
        buttonLabel: 'مراجعة الخصومات',
        buttonTone: 'primary',
        action: 'finalize-settlement',
    };
};

const resolveDepositUnpaidItem = (
    securityDeposit: SecurityDepositData,
    currencySymbol: string
): ActionCenterItem | null => {
    if (!securityDeposit.actions.can_record_payment || securityDeposit.outstanding_amount <= 0) {
        return null;
    }

    return {
        id: 'deposit-unpaid',
        priority: 6,
        title: `متبقٍ تحصيل ${formatMoney(securityDeposit.outstanding_amount)} ${currencySymbol} من التأمين`,
        description: `استُلم ${formatMoney(securityDeposit.total_received)} من أصل ${formatMoney(securityDeposit.agreed_amount)} ${currencySymbol} متفق عليه.`,
        badge: 'تأمين معلق',
        badgeTone: 'amber',
        buttonLabel: 'تسجيل استلام دفعة',
        buttonTone: 'secondary',
        action: 'record-deposit-payment',
    };
};

export const buildActionCenterItems = (
    lease: LeaseData,
    rentTotals: RentTotals,
    rentCharges: RentChargeItem[],
    securityDeposit: SecurityDepositData,
    remainingDays: number,
    currencySymbol: string
): ActionCenterItem[] => {
    const isLeaseActive = lease.status === 'active';
    const resolvers: Array<() => ActionCenterItem | null> = [
        () => resolveOverdueItem(rentTotals, isLeaseActive, currencySymbol),
        () => resolveUpcomingChargeItem(rentCharges, isLeaseActive, currencySymbol),
        () => resolveLeaseExpiryItem(lease, remainingDays, isLeaseActive),
        () => resolveDepositPendingRefundItem(securityDeposit, currencySymbol),
        () => resolveDepositReviewItem(securityDeposit),
        () => resolveDepositUnpaidItem(securityDeposit, currencySymbol),
    ];

    const items: ActionCenterItem[] = [];
    for (const resolver of resolvers) {
        if (items.length >= 3) {
            break;
        }
        const item = resolver();
        if (item) {
            items.push(item);
        }
    }

    return items;
};

export const getDepositHumanSummary = (
    deposit: SecurityDepositData,
    currencySymbol: string
): string => {
    if (deposit.agreed_amount <= 0 && deposit.total_received <= 0) {
        return 'لم يُشترط مبلغ تأمين في هذا العقد.';
    }

    if (deposit.total_received <= 0) {
        return `لم يتم تحصيل مبلغ التأمين المتفق عليه (${formatMoney(deposit.agreed_amount)} ${currencySymbol}) بعد.`;
    }

    if (deposit.total_deductions <= 0 && deposit.total_refunded <= 0) {
        return `استلمت ${formatMoney(deposit.total_received)} ${currencySymbol} بالكامل، وهي محفوظة كأمانة حتى موعد إخلاء وتسوية الوحدة.`;
    }

    if (deposit.total_deductions > 0 && deposit.total_refunded <= 0) {
        return `استلمت ${formatMoney(deposit.total_received)} ${currencySymbol}، خُصم ${formatMoney(deposit.total_deductions)} ${currencySymbol} مقابل بنود التسوية، والمتبقي ${formatMoney(deposit.refundable_balance)} ${currencySymbol} سيُردّ للمستأجر.`;
    }

    return `استلمت ${formatMoney(deposit.total_received)} ${currencySymbol}، خُصم ${formatMoney(deposit.total_deductions)} ${currencySymbol}، وصُرف ${formatMoney(deposit.total_refunded)} ${currencySymbol} للمستأجر (المتبقي: ${formatMoney(deposit.refundable_balance)} ${currencySymbol}).`;
};
