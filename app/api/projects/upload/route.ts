import { NextRequest, NextResponse } from 'next/server';
import { writeFile, mkdir, unlink } from 'fs/promises';
import path from 'path';
import { v4 as uuidv4 } from 'uuid';
import { getServerSession } from 'next-auth';
import { authOptions } from '../../auth/[...nextauth]/route';

export async function POST(request: NextRequest) {
  try {
    // Get user session
    const session = await getServerSession(authOptions);
    if (!session?.user?.id) {
      return NextResponse.json(
        { message: 'Unauthorized' },
        { status: 401 }
      );
    }

    const uploadFormData = await request.formData();
    const file = uploadFormData.get('zipFile') as File;
    const name = uploadFormData.get('name') as string;
    const description = uploadFormData.get('description') as string;
    const databaseName = uploadFormData.get('database_name') as string;

    if (!file || !name || !databaseName) {
      return NextResponse.json(
        { message: 'Missing required fields: zipFile, name, or database_name' },
        { status: 400 }
      );
    }

    // Validate database name
    if (!/^[a-zA-Z0-9_]+$/.test(databaseName)) {
      return NextResponse.json(
        { message: 'Database name can only contain letters, numbers, and underscores' },
        { status: 400 }
      );
    }

    // Generate a unique ID for the upload
    const uploadId = uuidv4();
    
    // Create temp directory if it doesn't exist
    const tempDir = path.join(process.cwd(), 'temp');
    try {
      await mkdir(tempDir, { recursive: true });
    } catch (error) {
      // Ignore error if directory already exists
    }
    
    // Create a temporary path for the zip file
    const tempZipPath = path.join(tempDir, `${uploadId}.zip`);
    
    // Convert the file to a Buffer
    const bytes = await file.arrayBuffer();
    const buffer = Buffer.from(bytes);

    // Save the file temporarily
    await writeFile(tempZipPath, buffer);

    try {
      // Create form data for PHP
      const form = new FormData();
      form.append('zipFile', new Blob([buffer], { type: 'application/zip' }), file.name);
      form.append('uploadId', uploadId);
      form.append('userId', session.user.id);
      form.append('name', name);
      form.append('database_name', databaseName);
      if (description) {
        form.append('description', description);
      }

      // Send to PHP backend
      const response = await fetch(`${process.env.API_URL}/api/projects/process.php`, {
        method: 'POST',
        body: form,
      });

      // Get response text first for debugging
      const responseText = await response.text();
      console.log('PHP Response:', responseText);

      let result;
      try {
        result = JSON.parse(responseText);
      } catch (error) {
        console.error('Failed to parse PHP response:', responseText);
        throw new Error('Invalid response from server');
      }

      if (!response.ok || !result.success) {
        throw new Error(result.message || 'Failed to process project');
      }

      // Clean up temporary file
      try {
        await unlink(tempZipPath);
      } catch (error) {
        console.error('Failed to delete temporary file:', error);
      }

      return NextResponse.json(result);
    } catch (error: any) {
      console.error('PHP processing error:', error);
      return NextResponse.json(
        { success: false, message: error.message },
        { status: 500 }
      );
    }
  } catch (error: any) {
    console.error('Upload error:', error);
    return NextResponse.json(
      { success: false, message: error.message },
      { status: 500 }
    );
  }
}
