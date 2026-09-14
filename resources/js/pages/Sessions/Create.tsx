import SessionFormPage from '@/pages/Sessions/SessionForm';

type UserOption = { id: string; display_name: string };

type Props = {
    users: UserOption[];
    sessionTypes: {
        value: string;
        label: string;
        tag: string;
        next_session_number: string;
        next_title: string;
        next_sequence: number;
    }[];
};

export default function SessionsCreate({ users, sessionTypes }: Props) {
    return <SessionFormPage users={users} sessionTypes={sessionTypes} />;
}
