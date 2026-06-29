Act as an AI Integration Architect and Senior CRM Strategist.

Your objective is to deeply analyze the current Perfex CRM (portal_18) codebase and propose a highly advanced AI integration strategy. This strategy must enhance a core business workflow by extracting hidden insights from customer interactions.

====================================================
STEP 1: WORKFLOW INSIGHT ANALYSIS
====================================================
Scan the database schema, models, and controllers specifically related to the "Leads", "Proposals", and "Tickets" (Customer Support) modules.

Identify bottlenecks or areas where human staff currently waste time on manual analysis, drafting, or data entry. Choose ONE specific workflow to optimize with AI.

====================================================
STEP 2: AI ARCHITECTURE DESIGN
====================================================
Propose a detailed technical design for integrating modern AI paradigms into the selected workflow. Your design MUST address the following advanced implementations:

1. RAG (Retrieval-Augmented Generation) Implementation:
   Design a pipeline to vectorize past successful Proposals, closed Tickets, and knowledge base articles. Explain how the system will retrieve this context to auto-suggest highly accurate, context-aware responses for the staff.

2. NLP & Prompt Engineering Pipeline:
   Map out how to utilize the existing Guzzle 6.x HTTP client to send text payloads (client emails, ticket descriptions, lead notes) to an external LLM (e.g., Gemini/Claude API). Design the system prompts required to enforce strict guardrails, define the AI's role, and extract exact data formats (like JSON) for intent classification.

3. Speech Processing & Energy Analytics Concept:
   Propose a visionary architectural concept for handling audio data within the CRM (e.g., recorded sales calls or voice notes attached to Leads). Detail a technical workflow where the system processes these audio files to extract physical sound properties (pitch, intensity, frequency). Explain how analyzing the energetic quality of the speech—differentiating the physical energy of positive vs. negative communication—could be quantified into a "Lead Confidence Score" or "Customer Frustration Index" to automatically prioritize the staff's dashboard.

====================================================
STEP 3: TECHNICAL ROADMAP & PROOF OF CONCEPT
====================================================
Output a structured markdown report containing:

- The Selected Workflow: (e.g., "The AI-Augmented Lead Lifecycle").
- Database Schema Additions: Necessary tables or columns for vector embeddings and sentiment/energy scores.
- Hook Integration Points: Specific CI3 `hooks()->do_action()` points where the AI pipeline should be triggered invisibly in the background.
- Risk Assessment: Potential latency issues, API rate limits, and data privacy concerns.
