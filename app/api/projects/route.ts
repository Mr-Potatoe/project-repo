import { getServerSession } from 'next-auth';
import { NextRequest, NextResponse } from 'next/server';
import { authOptions } from '../auth/[...nextauth]/route';

export async function GET() {
  try {
    const session = await getServerSession(authOptions);

    if (!session?.user?.sessionId) {
      return NextResponse.json({ message: 'Unauthorized' }, { status: 401 });
    }

    const apiUrl = process.env.API_URL || 'http://localhost/project-repo/backend';
    const response = await fetch(`${apiUrl}/api/projects/list.php`, {
      method: 'GET',
      credentials: 'include',
      headers: {
        'Cookie': `PHPSESSID=${session.user.sessionId}`,
      },
    });

    if (!response.ok) {
      const errorText = await response.text();
      throw new Error(`Failed to fetch projects: ${errorText}`);
    }

    const data = await response.json();
    return NextResponse.json(data);
  } catch (error) {
    console.error('Projects API error:', error);
    return NextResponse.json(
      { message: error instanceof Error ? error.message : 'Failed to fetch projects' },
      { status: 500 }
    );
  }
}
