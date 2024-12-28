import { getServerSession } from 'next-auth';
import { NextRequest, NextResponse } from 'next/server';
import { authOptions } from '../../auth/[...nextauth]/route';

export async function POST(request: NextRequest) {
  try {
    const session = await getServerSession(authOptions);
    console.log('Session in change-password:', session); // Debug session

    if (!session?.user?.sessionId) {
      console.log('No session or sessionId found'); // Debug session
      return NextResponse.json({ message: 'Unauthorized' }, { status: 401 });
    }

    const body = await request.json();
    console.log('Request body:', { ...body, newPassword: '[REDACTED]' }); // Debug body (safely)

    const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost';
    const endpoint = `${apiUrl}/project-repo/backend/api/users/change-password.php`;
    console.log('API URL:', endpoint); // Debug URL

    const response = await fetch(endpoint, {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'Cookie': `PHPSESSID=${session.user.sessionId}`,
      },
      body: JSON.stringify({
        currentPassword: body.current_password,
        newPassword: body.new_password,
      }),
    });

    console.log('PHP Response status:', response.status); // Debug response

    if (!response.ok) {
      const errorText = await response.text();
      console.error('PHP Error:', errorText); // Debug error
      throw new Error(errorText);
    }

    const data = await response.json();
    return NextResponse.json(data);
  } catch (error) {
    console.error('Change password error:', error); // Debug error
    return NextResponse.json(
      { message: error instanceof Error ? error.message : 'Failed to change password' },
      { status: 500 }
    );
  }
}
