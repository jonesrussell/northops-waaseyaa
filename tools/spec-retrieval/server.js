#!/usr/bin/env node

import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
} from "@modelcontextprotocol/sdk/types.js";
import { readFileSync, readdirSync } from "fs";
import { join, basename } from "path";

const SPECS_DIR = join(process.cwd(), "docs", "specs");

function loadSpecs() {
  const specs = {};
  try {
    const files = readdirSync(SPECS_DIR).filter((f) => f.endsWith(".md"));
    for (const file of files) {
      const name = basename(file, ".md");
      const content = readFileSync(join(SPECS_DIR, file), "utf-8");
      specs[name] = content;
    }
  } catch {
    // specs dir may not exist yet
  }
  return specs;
}

const server = new Server(
  { name: "northops-specs", version: "1.0.0" },
  { capabilities: { tools: {} } }
);

server.setRequestHandler(ListToolsRequestSchema, async () => ({
  tools: [
    {
      name: "northops_list_specs",
      description:
        "List all subsystem specification documents. Use this to discover which specs are available before retrieving one.",
      inputSchema: { type: "object", properties: {} },
    },
    {
      name: "northops_get_spec",
      description:
        "Retrieve the full content of a subsystem spec by name. Use after listing specs to get deep context on a subsystem.",
      inputSchema: {
        type: "object",
        properties: {
          name: {
            type: "string",
            description:
              "Spec name without .md extension, e.g. 'lead-pipeline-design', 'workflow'",
          },
        },
        required: ["name"],
      },
    },
    {
      name: "northops_search_specs",
      description:
        "Search all specs by keyword. Returns matching sections with surrounding context. Use when you need to find information across specs without knowing which one contains it.",
      inputSchema: {
        type: "object",
        properties: {
          query: {
            type: "string",
            description: "Keyword or phrase to search for across all specs",
          },
          max_results: {
            type: "number",
            description:
              "Maximum number of matching sections to return (default: 10)",
          },
        },
        required: ["query"],
      },
    },
  ],
}));

server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const { name, arguments: args } = request.params;
  const specs = loadSpecs();
  const specNames = Object.keys(specs);

  switch (name) {
    case "northops_list_specs": {
      if (specNames.length === 0) {
        return {
          content: [
            {
              type: "text",
              text: "No specs found in docs/specs/. Create .md files there to populate.",
            },
          ],
        };
      }
      const rows = specNames.map((n) => {
        const firstLine = specs[n].split("\n").find((l) => l.startsWith("# "));
        const title = firstLine ? firstLine.replace("# ", "") : n;
        return `| ${n} | ${title} |`;
      });
      const table = `| Name | Title |\n|------|-------|\n${rows.join("\n")}`;
      return { content: [{ type: "text", text: table }] };
    }

    case "northops_get_spec": {
      const specName = args?.name;
      if (!specName || !specs[specName]) {
        const available = specNames.join(", ") || "(none)";
        return {
          content: [
            {
              type: "text",
              text: `Spec "${specName}" not found. Available specs: ${available}`,
            },
          ],
        };
      }
      return { content: [{ type: "text", text: specs[specName] }] };
    }

    case "northops_search_specs": {
      const query = (args?.query || "").toLowerCase();
      const maxResults = args?.max_results || 10;
      const matches = [];

      for (const [specName, content] of Object.entries(specs)) {
        const lines = content.split("\n");
        for (let i = 0; i < lines.length; i++) {
          if (lines[i].toLowerCase().includes(query)) {
            const start = Math.max(0, i - 2);
            const end = Math.min(lines.length, i + 3);
            const context = lines.slice(start, end).join("\n");
            matches.push({ spec: specName, line: i + 1, context });
            if (matches.length >= maxResults) break;
          }
        }
        if (matches.length >= maxResults) break;
      }

      if (matches.length === 0) {
        return {
          content: [
            {
              type: "text",
              text: `No matches for "${args?.query}" across ${specNames.length} spec(s).`,
            },
          ],
        };
      }

      const output = matches
        .map(
          (m) =>
            `### ${m.spec} (line ${m.line})\n\`\`\`\n${m.context}\n\`\`\``
        )
        .join("\n\n");
      return { content: [{ type: "text", text: output }] };
    }

    default:
      return {
        content: [{ type: "text", text: `Unknown tool: ${name}` }],
        isError: true,
      };
  }
});

const transport = new StdioServerTransport();
await server.connect(transport);
