'use client';

import { useState, useEffect } from 'react';
import {
  Box,
  Typography,
  Paper,
  Grid,
  TextField,
  Button,
  Alert,
  CircularProgress,
  IconButton,
} from '@mui/material';
import { Add as AddIcon, Delete as DeleteIcon } from '@mui/icons-material';

interface Setting {
  id: number;
  key: string;
  value: string;
}

export default function SettingsPage() {
  const [settings, setSettings] = useState<Setting[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [newSetting, setNewSetting] = useState({ key: '', value: '' });

  useEffect(() => {
    fetchSettings();
  }, []);

  const fetchSettings = async () => {
    try {
      const response = await fetch('/api/admin/settings');
      const data = await response.json();
      if (data.status === 'success') {
        setSettings(data.data);
      } else {
        setError(data.message || 'Failed to fetch settings');
      }
    } catch (error) {
      setError('An error occurred while fetching settings');
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async (setting: Setting) => {
    try {
      const response = await fetch(`/api/admin/settings/${setting.id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(setting),
      });
      const data = await response.json();
      
      if (data.status === 'success') {
        setSuccess('Setting updated successfully');
        setTimeout(() => setSuccess(''), 3000);
      } else {
        setError(data.message || 'Failed to update setting');
      }
    } catch (error) {
      setError('An error occurred while updating the setting');
    }
  };

  const handleAdd = async () => {
    if (!newSetting.key || !newSetting.value) {
      setError('Both key and value are required');
      return;
    }

    try {
      const response = await fetch('/api/admin/settings', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(newSetting),
      });
      const data = await response.json();
      
      if (data.status === 'success') {
        setSettings([...settings, data.data]);
        setNewSetting({ key: '', value: '' });
        setSuccess('Setting added successfully');
        setTimeout(() => setSuccess(''), 3000);
      } else {
        setError(data.message || 'Failed to add setting');
      }
    } catch (error) {
      setError('An error occurred while adding the setting');
    }
  };

  const handleDelete = async (id: number) => {
    if (!confirm('Are you sure you want to delete this setting?')) return;

    try {
      const response = await fetch(`/api/admin/settings/${id}`, {
        method: 'DELETE',
      });
      const data = await response.json();
      
      if (data.status === 'success') {
        setSettings(settings.filter(setting => setting.id !== id));
        setSuccess('Setting deleted successfully');
        setTimeout(() => setSuccess(''), 3000);
      } else {
        setError(data.message || 'Failed to delete setting');
      }
    } catch (error) {
      setError('An error occurred while deleting the setting');
    }
  };

  if (loading) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', mt: 4 }}>
        <CircularProgress />
      </Box>
    );
  }

  return (
    <Box>
      <Typography variant="h4" gutterBottom>
        Settings
      </Typography>

      {error && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {error}
        </Alert>
      )}

      {success && (
        <Alert severity="success" sx={{ mb: 2 }}>
          {success}
        </Alert>
      )}

      <Paper sx={{ p: 2, mb: 4 }}>
        <Typography variant="h6" gutterBottom>
          Add New Setting
        </Typography>
        <Grid container spacing={2} alignItems="center">
          <Grid item xs={12} sm={5}>
            <TextField
              fullWidth
              label="Key"
              value={newSetting.key}
              onChange={(e) => setNewSetting({ ...newSetting, key: e.target.value })}
            />
          </Grid>
          <Grid item xs={12} sm={5}>
            <TextField
              fullWidth
              label="Value"
              value={newSetting.value}
              onChange={(e) => setNewSetting({ ...newSetting, value: e.target.value })}
            />
          </Grid>
          <Grid item xs={12} sm={2}>
            <Button
              fullWidth
              variant="contained"
              startIcon={<AddIcon />}
              onClick={handleAdd}
            >
              Add
            </Button>
          </Grid>
        </Grid>
      </Paper>

      <Paper sx={{ p: 2 }}>
        <Typography variant="h6" gutterBottom>
          Current Settings
        </Typography>
        {settings.map((setting) => (
          <Box key={setting.id} sx={{ mb: 2 }}>
            <Grid container spacing={2} alignItems="center">
              <Grid item xs={12} sm={5}>
                <TextField
                  fullWidth
                  label="Key"
                  value={setting.key}
                  onChange={(e) => {
                    const updatedSettings = settings.map(s =>
                      s.id === setting.id ? { ...s, key: e.target.value } : s
                    );
                    setSettings(updatedSettings);
                  }}
                />
              </Grid>
              <Grid item xs={12} sm={5}>
                <TextField
                  fullWidth
                  label="Value"
                  value={setting.value}
                  onChange={(e) => {
                    const updatedSettings = settings.map(s =>
                      s.id === setting.id ? { ...s, value: e.target.value } : s
                    );
                    setSettings(updatedSettings);
                  }}
                />
              </Grid>
              <Grid item xs={12} sm={2}>
                <Box sx={{ display: 'flex', gap: 1 }}>
                  <Button
                    variant="contained"
                    onClick={() => handleSave(setting)}
                  >
                    Save
                  </Button>
                  <IconButton
                    color="error"
                    onClick={() => handleDelete(setting.id)}
                  >
                    <DeleteIcon />
                  </IconButton>
                </Box>
              </Grid>
            </Grid>
          </Box>
        ))}
      </Paper>
    </Box>
  );
}
