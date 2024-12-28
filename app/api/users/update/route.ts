import { NextRequest, NextResponse } from 'next/server';
import { getServerSession } from 'next-auth';
import { authOptions } from '../../auth/[...nextauth]/route';

export async function PUT(request: NextRequest) {
  try {
    // Check if user is authenticated and is admin
    const session = await getServerSession(authOptions);
    if (!session?.user?.role === 'admin') {
      return NextResponse.json(
        { message: 'Unauthorized' },
        { status: 401 }
      );
    }

    const data = await request.json();

    // Validate required fields
    if (!data.id || !data.username || !data.email || !data.role) {
      return NextResponse.json(
        { status: 'error', message: 'Missing required fields' },
        { status: 400 }
      );
    }

    // Update user in PHP backend
    const response = await fetch(`${process.env.API_URL}/api/users/update.php`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(data),
    });

    const result = await response.json();
    return NextResponse.json(result, { 
      status: result.status === 'success' ? 200 : 400 
    });
  } catch (error) {
    console.error('Error updating user:', error);
    return NextResponse.json(
      { status: 'error', message: 'Failed to update user' },
      { status: 500 }
    );
  }
}
