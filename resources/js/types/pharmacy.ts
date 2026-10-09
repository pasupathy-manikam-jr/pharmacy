export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type PoisonGroup = 'none' | 'B' | 'C' | 'D' | 'psychotropic' | 'dda';

export type Product = {
    id: number;
    name: string;
    generic_name: string | null;
    strength: string | null;
    form: string | null;
    poison_group: PoisonGroup;
    barcode: string | null;
    mal_reg_no: string | null;
    unit: string;
    price_sen: number;
    tax_rate_bp: number;
    reorder_level: number;
    is_active: boolean;
};

export type Supplier = {
    id: number;
    name: string;
    tin: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
};

export type Customer = {
    id: number;
    name: string;
    ic_no: string | null;
    dob: string | null;
    sex: string | null;
    phone: string | null;
    address: string | null;
    citizenship: string | null;
    allergies: string | null;
    tin?: string | null;
    brn?: string | null;
    email?: string | null;
};

export type Option<T extends string | number = number> = {
    value: T;
    label: string;
    hint?: string;
};

export type SortState = { sort: string; dir: 'asc' | 'desc' };
