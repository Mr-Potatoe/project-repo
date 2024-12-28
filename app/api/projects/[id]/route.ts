import { NextRequest, NextResponse } from 'next/server';
import { getServerSession } from 'next-auth';
import { authOptions } from '../../auth/[...nextauth]/route';

export async function PUT(
  request: NextRequest,
  { params }: { params: { id: string } }
) {
  try {
    const session = await getServerSession(authOptions);

    if (!session?.user?.sessionId) {
      return NextResponse.json({ message: 'Unauthorized' }, { status: 401 });
    }

    const id = params.id;
    const body = await request.json();

    const response = await fetch(
      `${process.env.API_URL}/api/projects/manage.php?id=${id}`,
      {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Cookie': `PHPSESSID=${session.user.sessionId}`,
        },
        body: JSON.stringify(body),
        credentials: 'include',
      }
    );

    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.message || 'Failed to update project');
    }

    return NextResponse.json(data);
  } catch (error) {
    console.error('Error updating project:', error);
    return NextResponse.json(
      { message: error instanceof Error ? error.message : 'Failed to update project' },
      { status: 500 }
    );
  }
}

export async function DELETE(
  request: NextRequest,
  { params }: { params: { id: string } }
) {
  try {
    const session = await getServerSession(authOptions);

    if (!session?.user?.sessionId) {
      return NextResponse.json({ message: 'Unauthorized' }, { status: 401 });
    }

    const id = params.id;

    const response = await fetch(
      `${process.env.API_URL}/api/projects/manage.php?id=${id}`,
      {
        method: 'DELETE',
        headers: {
          'Cookie': `PHPSESSID=${session.user.sessionId}`,
        },
        credentials: 'include',
      }
    );

    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.message || 'Failed to delete project');
    }

    return NextResponse.json(data);
  } catch (error) {
    console.error('Error deleting project:', error);
    return NextResponse.json(
      { message: error instanceof Error ? error.message : 'Failed to delete project' },
      { status: 500 }
    );
  }
}
