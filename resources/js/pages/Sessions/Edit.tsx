import SessionFormPage, { type SessionFormRecord } from '@/pages/Sessions/SessionForm';

type UserOption = { id: string; display_name: string };

type Props = {
    session: SessionFormRecord;
    users: UserOption[];
};

export default function SessionsEdit({ session, users }: Props) {
    return <SessionFormPage session={session} users={users} />;
}
