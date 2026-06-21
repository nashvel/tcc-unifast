export type Json =
  | string
  | number
  | boolean
  | null
  | { [key: string]: Json | undefined }
  | Json[]

export type Database = {
  // Allows to automatically instantiate createClient with right options
  // instead of createClient<Database, { PostgrestVersion: 'XX' }>(URL, KEY)
  __InternalSupabase: {
    PostgrestVersion: "14.5"
  }
  public: {
    Tables: {
      academic_records: {
        Row: {
          cumulative_gwa: number | null
          grantee_id: string
          grantee_name: string
          program: string | null
          recommendation: string
          retention_passed: boolean
          semesters: Json
          student_number: string
          updated_at: string
        }
        Insert: {
          cumulative_gwa?: number | null
          grantee_id: string
          grantee_name: string
          program?: string | null
          recommendation?: string
          retention_passed?: boolean
          semesters?: Json
          student_number: string
          updated_at?: string
        }
        Update: {
          cumulative_gwa?: number | null
          grantee_id?: string
          grantee_name?: string
          program?: string | null
          recommendation?: string
          retention_passed?: boolean
          semesters?: Json
          student_number?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "academic_records_grantee_id_fkey"
            columns: ["grantee_id"]
            isOneToOne: true
            referencedRelation: "grantees"
            referencedColumns: ["id"]
          },
        ]
      }
      announcements: {
        Row: {
          audience: string
          audience_label: string | null
          author: string | null
          body: string
          channels: string[]
          created_at: string
          id: string
          opens: number | null
          published_at: string | null
          reach: number | null
          scheduled_for: string | null
          status: string
          title: string
        }
        Insert: {
          audience?: string
          audience_label?: string | null
          author?: string | null
          body: string
          channels?: string[]
          created_at?: string
          id: string
          opens?: number | null
          published_at?: string | null
          reach?: number | null
          scheduled_for?: string | null
          status?: string
          title: string
        }
        Update: {
          audience?: string
          audience_label?: string | null
          author?: string | null
          body?: string
          channels?: string[]
          created_at?: string
          id?: string
          opens?: number | null
          published_at?: string | null
          reach?: number | null
          scheduled_for?: string | null
          status?: string
          title?: string
        }
        Relationships: []
      }
      audit_logs: {
        Row: {
          action: string
          after: Json | null
          before: Json | null
          id: string
          ip: string | null
          module: string
          role: string
          target: string
          timestamp: string
          user: string
        }
        Insert: {
          action: string
          after?: Json | null
          before?: Json | null
          id: string
          ip?: string | null
          module: string
          role: string
          target: string
          timestamp?: string
          user: string
        }
        Update: {
          action?: string
          after?: Json | null
          before?: Json | null
          id?: string
          ip?: string | null
          module?: string
          role?: string
          target?: string
          timestamp?: string
          user?: string
        }
        Relationships: []
      }
      batches: {
        Row: {
          academic_year: string
          active: number
          created_at: string
          id: string
          name: string
          pending: number
          semester: string
          status: string
          total_grantees: number
          validated: number
        }
        Insert: {
          academic_year: string
          active?: number
          created_at?: string
          id: string
          name: string
          pending?: number
          semester: string
          status?: string
          total_grantees?: number
          validated?: number
        }
        Update: {
          academic_year?: string
          active?: number
          created_at?: string
          id?: string
          name?: string
          pending?: number
          semester?: string
          status?: string
          total_grantees?: number
          validated?: number
        }
        Relationships: []
      }
      documents: {
        Row: {
          exif: Json | null
          filename: string
          grantee_name: string
          id: string
          ocr: Json | null
          owner_id: string | null
          remarks: string | null
          risk_score: number
          status: string
          student_number: string
          type: string
          uploaded_at: string
        }
        Insert: {
          exif?: Json | null
          filename: string
          grantee_name: string
          id?: string
          ocr?: Json | null
          owner_id?: string | null
          remarks?: string | null
          risk_score?: number
          status?: string
          student_number: string
          type: string
          uploaded_at?: string
        }
        Update: {
          exif?: Json | null
          filename?: string
          grantee_name?: string
          id?: string
          ocr?: Json | null
          owner_id?: string | null
          remarks?: string | null
          risk_score?: number
          status?: string
          student_number?: string
          type?: string
          uploaded_at?: string
        }
        Relationships: []
      }
      grantees: {
        Row: {
          account_status: string
          batch: string | null
          batch_id: string | null
          birthdate: string | null
          contact: string | null
          created_at: string
          eligibility: string
          email: string | null
          first_name: string
          gwa: number | null
          id: string
          last_name: string
          middle_name: string | null
          notes: string | null
          profile_completion: number
          program: string | null
          risk: string
          student_number: string
          submission_status: string
          university: string | null
          year_level: number | null
        }
        Insert: {
          account_status?: string
          batch?: string | null
          batch_id?: string | null
          birthdate?: string | null
          contact?: string | null
          created_at?: string
          eligibility?: string
          email?: string | null
          first_name: string
          gwa?: number | null
          id: string
          last_name: string
          middle_name?: string | null
          notes?: string | null
          profile_completion?: number
          program?: string | null
          risk?: string
          student_number: string
          submission_status?: string
          university?: string | null
          year_level?: number | null
        }
        Update: {
          account_status?: string
          batch?: string | null
          batch_id?: string | null
          birthdate?: string | null
          contact?: string | null
          created_at?: string
          eligibility?: string
          email?: string | null
          first_name?: string
          gwa?: number | null
          id?: string
          last_name?: string
          middle_name?: string | null
          notes?: string | null
          profile_completion?: number
          program?: string | null
          risk?: string
          student_number?: string
          submission_status?: string
          university?: string | null
          year_level?: number | null
        }
        Relationships: [
          {
            foreignKeyName: "grantees_batch_id_fkey"
            columns: ["batch_id"]
            isOneToOne: false
            referencedRelation: "batches"
            referencedColumns: ["id"]
          },
        ]
      }
      masterlist: {
        Row: {
          account_status: string
          batch: string | null
          birthdate: string | null
          contact: string | null
          email: string | null
          first_name: string
          id: string
          imported_at: string
          last_name: string
          middle_name: string | null
          program: string | null
          student_number: string
          university: string | null
          year_level: number | null
        }
        Insert: {
          account_status?: string
          batch?: string | null
          birthdate?: string | null
          contact?: string | null
          email?: string | null
          first_name: string
          id?: string
          imported_at?: string
          last_name: string
          middle_name?: string | null
          program?: string | null
          student_number: string
          university?: string | null
          year_level?: number | null
        }
        Update: {
          account_status?: string
          batch?: string | null
          birthdate?: string | null
          contact?: string | null
          email?: string | null
          first_name?: string
          id?: string
          imported_at?: string
          last_name?: string
          middle_name?: string | null
          program?: string | null
          student_number?: string
          university?: string | null
          year_level?: number | null
        }
        Relationships: []
      }
      notifications: {
        Row: {
          body: string | null
          created_at: string
          id: string
          read: boolean
          title: string
          type: string
          user_id: string
        }
        Insert: {
          body?: string | null
          created_at?: string
          id?: string
          read?: boolean
          title: string
          type?: string
          user_id: string
        }
        Update: {
          body?: string | null
          created_at?: string
          id?: string
          read?: boolean
          title?: string
          type?: string
          user_id?: string
        }
        Relationships: []
      }
      profiles: {
        Row: {
          avatar_url: string | null
          birthdate: string | null
          contact: string | null
          created_at: string
          email: string | null
          full_name: string
          id: string
          onboarding_completed_at: string | null
          program: string | null
          student_number: string | null
          university: string | null
          year_level: number | null
        }
        Insert: {
          avatar_url?: string | null
          birthdate?: string | null
          contact?: string | null
          created_at?: string
          email?: string | null
          full_name?: string
          id: string
          onboarding_completed_at?: string | null
          program?: string | null
          student_number?: string | null
          university?: string | null
          year_level?: number | null
        }
        Update: {
          avatar_url?: string | null
          birthdate?: string | null
          contact?: string | null
          created_at?: string
          email?: string | null
          full_name?: string
          id?: string
          onboarding_completed_at?: string | null
          program?: string | null
          student_number?: string | null
          university?: string | null
          year_level?: number | null
        }
        Relationships: []
      }
      security_memory_entries: {
        Row: {
          body: string
          category: Database["public"]["Enums"]["security_memory_category"]
          created_at: string
          created_by: string | null
          id: string
          related_finding_id: string | null
          status: Database["public"]["Enums"]["security_memory_status"]
          title: string
          updated_at: string
          updated_by: string | null
          version: number
        }
        Insert: {
          body: string
          category?: Database["public"]["Enums"]["security_memory_category"]
          created_at?: string
          created_by?: string | null
          id?: string
          related_finding_id?: string | null
          status?: Database["public"]["Enums"]["security_memory_status"]
          title: string
          updated_at?: string
          updated_by?: string | null
          version?: number
        }
        Update: {
          body?: string
          category?: Database["public"]["Enums"]["security_memory_category"]
          created_at?: string
          created_by?: string | null
          id?: string
          related_finding_id?: string | null
          status?: Database["public"]["Enums"]["security_memory_status"]
          title?: string
          updated_at?: string
          updated_by?: string | null
          version?: number
        }
        Relationships: []
      }
      security_memory_revisions: {
        Row: {
          body: string
          category: Database["public"]["Enums"]["security_memory_category"]
          change_summary: string | null
          changed_by: string | null
          created_at: string
          entry_id: string
          id: string
          related_finding_id: string | null
          status: Database["public"]["Enums"]["security_memory_status"]
          title: string
          version: number
        }
        Insert: {
          body: string
          category: Database["public"]["Enums"]["security_memory_category"]
          change_summary?: string | null
          changed_by?: string | null
          created_at?: string
          entry_id: string
          id?: string
          related_finding_id?: string | null
          status: Database["public"]["Enums"]["security_memory_status"]
          title: string
          version: number
        }
        Update: {
          body?: string
          category?: Database["public"]["Enums"]["security_memory_category"]
          change_summary?: string | null
          changed_by?: string | null
          created_at?: string
          entry_id?: string
          id?: string
          related_finding_id?: string | null
          status?: Database["public"]["Enums"]["security_memory_status"]
          title?: string
          version?: number
        }
        Relationships: [
          {
            foreignKeyName: "security_memory_revisions_entry_id_fkey"
            columns: ["entry_id"]
            isOneToOne: false
            referencedRelation: "security_memory_entries"
            referencedColumns: ["id"]
          },
        ]
      }
      staff_directory: {
        Row: {
          active: boolean
          created_at: string
          email: string
          full_name: string
          id: string
          last_login: string | null
          mfa: boolean
          role: string
          username: string
        }
        Insert: {
          active?: boolean
          created_at?: string
          email: string
          full_name: string
          id: string
          last_login?: string | null
          mfa?: boolean
          role: string
          username: string
        }
        Update: {
          active?: boolean
          created_at?: string
          email?: string
          full_name?: string
          id?: string
          last_login?: string | null
          mfa?: boolean
          role?: string
          username?: string
        }
        Relationships: []
      }
      user_roles: {
        Row: {
          id: string
          role: Database["public"]["Enums"]["app_role"]
          user_id: string
        }
        Insert: {
          id?: string
          role: Database["public"]["Enums"]["app_role"]
          user_id: string
        }
        Update: {
          id?: string
          role?: Database["public"]["Enums"]["app_role"]
          user_id?: string
        }
        Relationships: []
      }
    }
    Views: {
      [_ in never]: never
    }
    Functions: {
      has_role: {
        Args: {
          _role: Database["public"]["Enums"]["app_role"]
          _user_id: string
        }
        Returns: boolean
      }
      is_staff: { Args: { _user_id: string }; Returns: boolean }
    }
    Enums: {
      app_role: "admin" | "staff" | "head" | "student"
      security_memory_category:
        | "invariant"
        | "scanner_guidance"
        | "accepted_risk"
        | "note"
      security_memory_status: "active" | "archived"
    }
    CompositeTypes: {
      [_ in never]: never
    }
  }
}

type DatabaseWithoutInternals = Omit<Database, "__InternalSupabase">

type DefaultSchema = DatabaseWithoutInternals[Extract<keyof Database, "public">]

export type Tables<
  DefaultSchemaTableNameOrOptions extends
    | keyof (DefaultSchema["Tables"] & DefaultSchema["Views"])
    | { schema: keyof DatabaseWithoutInternals },
  TableName extends DefaultSchemaTableNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof (DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"] &
        DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Views"])
    : never = never,
> = DefaultSchemaTableNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? (DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"] &
      DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Views"])[TableName] extends {
      Row: infer R
    }
    ? R
    : never
  : DefaultSchemaTableNameOrOptions extends keyof (DefaultSchema["Tables"] &
        DefaultSchema["Views"])
    ? (DefaultSchema["Tables"] &
        DefaultSchema["Views"])[DefaultSchemaTableNameOrOptions] extends {
        Row: infer R
      }
      ? R
      : never
    : never

export type TablesInsert<
  DefaultSchemaTableNameOrOptions extends
    | keyof DefaultSchema["Tables"]
    | { schema: keyof DatabaseWithoutInternals },
  TableName extends DefaultSchemaTableNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"]
    : never = never,
> = DefaultSchemaTableNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"][TableName] extends {
      Insert: infer I
    }
    ? I
    : never
  : DefaultSchemaTableNameOrOptions extends keyof DefaultSchema["Tables"]
    ? DefaultSchema["Tables"][DefaultSchemaTableNameOrOptions] extends {
        Insert: infer I
      }
      ? I
      : never
    : never

export type TablesUpdate<
  DefaultSchemaTableNameOrOptions extends
    | keyof DefaultSchema["Tables"]
    | { schema: keyof DatabaseWithoutInternals },
  TableName extends DefaultSchemaTableNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"]
    : never = never,
> = DefaultSchemaTableNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"][TableName] extends {
      Update: infer U
    }
    ? U
    : never
  : DefaultSchemaTableNameOrOptions extends keyof DefaultSchema["Tables"]
    ? DefaultSchema["Tables"][DefaultSchemaTableNameOrOptions] extends {
        Update: infer U
      }
      ? U
      : never
    : never

export type Enums<
  DefaultSchemaEnumNameOrOptions extends
    | keyof DefaultSchema["Enums"]
    | { schema: keyof DatabaseWithoutInternals },
  EnumName extends DefaultSchemaEnumNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof DatabaseWithoutInternals[DefaultSchemaEnumNameOrOptions["schema"]]["Enums"]
    : never = never,
> = DefaultSchemaEnumNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? DatabaseWithoutInternals[DefaultSchemaEnumNameOrOptions["schema"]]["Enums"][EnumName]
  : DefaultSchemaEnumNameOrOptions extends keyof DefaultSchema["Enums"]
    ? DefaultSchema["Enums"][DefaultSchemaEnumNameOrOptions]
    : never

export type CompositeTypes<
  PublicCompositeTypeNameOrOptions extends
    | keyof DefaultSchema["CompositeTypes"]
    | { schema: keyof DatabaseWithoutInternals },
  CompositeTypeName extends PublicCompositeTypeNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof DatabaseWithoutInternals[PublicCompositeTypeNameOrOptions["schema"]]["CompositeTypes"]
    : never = never,
> = PublicCompositeTypeNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? DatabaseWithoutInternals[PublicCompositeTypeNameOrOptions["schema"]]["CompositeTypes"][CompositeTypeName]
  : PublicCompositeTypeNameOrOptions extends keyof DefaultSchema["CompositeTypes"]
    ? DefaultSchema["CompositeTypes"][PublicCompositeTypeNameOrOptions]
    : never

export const Constants = {
  public: {
    Enums: {
      app_role: ["admin", "staff", "head", "student"],
      security_memory_category: [
        "invariant",
        "scanner_guidance",
        "accepted_risk",
        "note",
      ],
      security_memory_status: ["active", "archived"],
    },
  },
} as const
