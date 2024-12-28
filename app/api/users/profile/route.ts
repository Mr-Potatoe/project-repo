import { getServerSession } from 'next-auth';
import { NextRequest, NextResponse } from 'next/server';
import { authOptions } from '../../auth/[...nextauth]/route';

export async function GET() {
  try {
    const session = await getServerSession(authOptions);
    console.log('Session:', session); // Debug session

    if (!session?.user?.sessionId) {
      console.log('No session or sessionId found'); // Debug session
      return NextResponse.json({ message: 'Unauthorized' }, { status: 401 });
    }

    const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost';
    const endpoint = `${apiUrl}/project-repo/backend/api/users/profile.php`;
    console.log('API URL:', endpoint); // Debug URL

    const response = await fetch(endpoint, {
      method: 'GET',
      credentials: 'include',
      headers: {
        'Cookie': `PHPSESSID=${session.user.sessionId}`,
      },
    });

    console.log('PHP Response status:', response.status); // Debug response

    if (!response.ok) {
      const errorText = await response.text();
      console.error('PHP Error:', errorText); // Debug error
      throw new Error(`Failed to fetch profile: ${errorText}`);
    }

    const data = await response.json();
    return NextResponse.json(data);
  } catch (error) {
    console.error('Profile API error:', error); // Debug error
    return NextResponse.json(
      { message: error instanceof Error ? error.message : 'Failed to fetch profile' },
      { status: 500 }
    );
  }
}

export async function PUT(request: NextRequest) {
  try {
    const session = await getServerSession(authOptions);
    console.log('Session for PUT:', session); // Debug session

    if (!session?.user?.sessionId) {
      return NextResponse.json({ message: 'Unauthorized' }, { status: 401 });
    }

    const body = await request.json();
    const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost';
    const endpoint = `${apiUrl}/project-repo/backend/api/users/profile.php`;

    const response = await fetch(endpoint, {
      method: 'PUT',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'Cookie': `PHPSESSID=${session.user.sessionId}`,
      },
      body: JSON.stringify(body),
    });

    if (!response.ok) {
      const errorText = await response.text();
      throw new Error(errorText);
    }

    const data = await response.json();
    return NextResponse.json(data);
  } catch (error) {
    console.error('Profile update error:', error);
    return NextResponse.json(
      { message: error instanceof Error ? error.message : 'Failed to update profile' },
      { status: 500 }
    );
  }
}
