export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Role = 'owner' | 'pharmacist' | 'assistant' | 'cashier';

export type Branch = {
    id: number;
    name: string;
    licence_no: string | null;
    address: string | null;
    phone: string | null;
};

export type Auth = {
    user: User;
    roles: Role[];
    branch: Branch | null;
    branches: Pick<Branch, 'id' | 'name'>[];
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
