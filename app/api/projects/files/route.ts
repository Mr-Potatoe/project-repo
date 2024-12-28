import { NextRequest, NextResponse } from 'next/server';
import { getServerSession } from 'next-auth';
import { authOptions } from '@/app/api/auth/[...nextauth]/route';

export async function GET(req: NextRequest) {
  try {
    const session = await getServerSession(authOptions);
    if (!session) {
      return NextResponse.json({ message: 'Unauthorized' }, { status: 401 });
    }

    const { searchParams } = new URL(req.url);
    const projectId = searchParams.get('project_id');
    const path = searchParams.get('path') || '';

    if (!projectId) {
      return NextResponse.json({ message: 'Project ID is required' }, { status: 400 });
    }

    // Construct URL with query parameters
    const apiUrl = `${process.env.API_URL}/api/projects/files.php?project_id=${projectId}&path=${encodeURIComponent(path)}`;
    console.log('API URL:', apiUrl);
    console.log('Project ID:', projectId);
    console.log('Path:', path);

    const response = await fetch(apiUrl, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
      },
      cache: 'no-store',
    });

    console.log('Response status:', response.status);
    console.log('Response headers:', Object.fromEntries(response.headers.entries()));

    let data;
    try {
      const text = await response.text();
      console.log('Raw response:', text);
      data = JSON.parse(text);
    } catch (error) {
      console.error('Failed to parse JSON response:', error);
      throw new Error('Invalid JSON response from server');
    }

    if (!response.ok) {
      throw new Error(data.message || 'Failed to fetch files');
    }

    return NextResponse.json(data);
  } catch (error: any) {
    console.error('Error in GET /api/projects/files:', error);
    return NextResponse.json(
      { success: false, message: error.message || 'Failed to load files' },
      { status: 500 }
    );
  }
}

export async function PUT(req: NextRequest) {
  try {
    const session = await getServerSession(authOptions);
    if (!session) {
      return NextResponse.json({ message: 'Unauthorized' }, { status: 401 });
    }

    const body = await req.json();
    const { project_id, path, content } = body;

    if (!project_id || !path || content === undefined) {
      return NextResponse.json(
        { message: 'Project ID, path, and content are required' },
        { status: 400 }
      );
    }

    const apiUrl = `${process.env.API_URL}/api/projects/files.php`;
    console.log('API URL:', apiUrl);

    const response = await fetch(apiUrl, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({ project_id, path, content }),
      cache: 'no-store',
    });

    let data;
    try {
      const text = await response.text();
      console.log('Raw response:', text);
      data = JSON.parse(text);
    } catch (error) {
      console.error('Failed to parse JSON response:', error);
      throw new Error('Invalid JSON response from server');
    }

    if (!response.ok) {
      throw new Error(data.message || 'Failed to update file');
    }

    return NextResponse.json(data);
  } catch (error: any) {
    console.error('Error in PUT /api/projects/files:', error);
    return NextResponse.json(
      { success: false, message: error.message || 'Failed to update file' },
      { status: 500 }
    );
  }
}
