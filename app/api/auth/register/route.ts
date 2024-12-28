import { NextResponse } from 'next/server';
import axios from 'axios';

export async function POST(request: Request) {
  try {
    const body = await request.json();
    const { email, password, username, role } = body;

    if (!email || !password || !username) {
      return NextResponse.json(
        { message: 'Missing required fields' },
        { status: 400 }
      );
    }

    // Call PHP backend API
    const response = await axios.post(`${process.env.API_URL}/api/auth/register.php`, {
      email,
      password,
      username,
      role,
    });

    return NextResponse.json(response.data, {
      status: response.status,
    });
  } catch (error: any) {
    console.error('Registration error:', error.response?.data || error.message);
    
    // If it's an axios error with a response, use that status and message
    if (error.response) {
      return NextResponse.json(
        { message: error.response.data.message || 'Registration failed' },
        { status: error.response.status }
      );
    }

    // Default error response
    return NextResponse.json(
      { message: 'Registration failed' },
      { status: 500 }
    );
  }
}
