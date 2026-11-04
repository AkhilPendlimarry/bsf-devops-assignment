export const dynamic = 'force-dynamic';
export default function Home() {
  return (
    <main>
      <h1>BSF SaaS App</h1>
      <p>Served by: <strong>{process.env.INSTANCE_NAME || 'unknown'}</strong></p>
    </main>
  );
}
