import { NextResponse } from 'next/server';

export async function GET() {
  try {
    const apiUrl = process.env.API_URL || 'http://localhost/project-repo/backend';
    const url = `${apiUrl}/api/projects/public.php`;
    console.log('Fetching from URL:', url);

    const response = await fetch(url, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
      },
    });

    if (!response.ok) {
      const errorText = await response.text();
      console.error('API Response not OK:', {
        status: response.status,
        statusText: response.statusText,
        error: errorText
      });
      throw new Error(`Failed to fetch projects: ${errorText}`);
    }

    const text = await response.text();
    console.log('Raw response:', text);

    let data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      console.error('Failed to parse JSON:', e);
      throw new Error('Invalid JSON response from API');
    }

    console.log('Parsed data:', data);
    return NextResponse.json(data);
  } catch (error) {
    console.error('Public Projects API error:', error);
    return NextResponse.json(
      { message: error instanceof Error ? error.message : 'Failed to fetch projects' },
      { status: 500 }
    );
  }
}
