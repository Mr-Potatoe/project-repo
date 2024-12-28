import NextAuth, { AuthOptions } from 'next-auth';
import CredentialsProvider from 'next-auth/providers/credentials';
import axios from 'axios';

export const authOptions: AuthOptions = {
  providers: [
    CredentialsProvider({
      name: 'Credentials',
      credentials: {
        email: { label: "Email", type: "text" },
        password: { label: "Password", type: "password" }
      },
      async authorize(credentials) {
        try {
          const response = await axios.post(`${process.env.API_URL}/api/auth/login.php`, {
            email: credentials?.email,
            password: credentials?.password,
          });

          if (response.data.user) {
            // Extract PHP session ID from response headers
            const cookies = response.headers['set-cookie'];
            let phpSessionId = '';
            if (cookies) {
              const sessionCookie = cookies.find((cookie: string) => cookie.startsWith('PHPSESSID='));
              if (sessionCookie) {
                phpSessionId = sessionCookie.split(';')[0].split('=')[1];
              }
            }

            return {
              id: response.data.user.id.toString(),
              name: response.data.user.username,
              email: response.data.user.email,
              role: response.data.user.role,
              sessionId: phpSessionId, // Store PHP session ID
            };
          }
          return null;
        } catch (error) {
          console.error('Auth error:', error);
          return null;
        }
      }
    })
  ],
  pages: {
    signIn: '/auth/login',
  },
  session: {
    strategy: 'jwt',
    maxAge: 30 * 24 * 60 * 60, // 30 days
  },
  callbacks: {
    async jwt({ token, user }) {
      if (user) {
        token.id = user.id;
        token.role = user.role;
        token.sessionId = user.sessionId; // Pass session ID to token
      }
      return token;
    },
    async session({ session, token }) {
      if (session.user) {
        session.user.id = token.id as string;
        session.user.role = token.role as string;
        session.user.sessionId = token.sessionId as string; // Pass session ID to session
      }
      return session;
    }
  },
};

const handler = NextAuth(authOptions);

export { handler as GET, handler as POST };
