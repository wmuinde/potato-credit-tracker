
import { useState } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { ArrowUp, ArrowDown, TrendingUp, DollarSign, Users, FileText, Truck } from "lucide-react";

const Index = () => {
  const [hoveredCard, setHoveredCard] = useState<string | null>(null);

  // Sample stats for the dashboard
  const stats = [
    {
      id: "total_debt",
      title: "Total Outstanding Debt",
      value: "₦128,400",
      change: "+12.5%",
      trend: "up",
      description: "From last month",
      icon: DollarSign,
      color: "bg-amber-100 text-amber-800",
      iconColor: "text-amber-800",
    },
    {
      id: "debtors",
      title: "Active Debtors",
      value: "245",
      change: "+3.2%",
      trend: "up",
      description: "New debtors this month",
      icon: Users,
      color: "bg-sky-100 text-sky-800",
      iconColor: "text-sky-800",
    },
    {
      id: "collections",
      title: "Recent Collections",
      value: "₦58,200",
      change: "-5.1%",
      trend: "down",
      description: "Compared to last week",
      icon: FileText,
      color: "bg-green-100 text-green-800",
      iconColor: "text-green-800",
    },
    {
      id: "vehicles",
      title: "Active Vehicles",
      value: "18",
      change: "+2",
      trend: "up",
      description: "New vehicles this month",
      icon: Truck,
      color: "bg-purple-100 text-purple-800",
      iconColor: "text-purple-800",
    },
  ];

  const recentActivities = [
    { id: 1, title: "Payment Received", vehicle: "Lorry B23", amount: "₦12,500", time: "2 hours ago" },
    { id: 2, title: "New Customer", vehicle: "Store North", amount: "", time: "5 hours ago" },
    { id: 3, title: "Debt Added", vehicle: "Lorry A45", amount: "₦45,000", time: "Yesterday" },
    { id: 4, title: "Payment Forwarded", vehicle: "Store West", amount: "₦28,300", time: "Yesterday" },
    { id: 5, title: "Expense Recorded", vehicle: "Lorry C12", amount: "₦8,200", time: "2 days ago" },
  ];

  return (
    <div className="min-h-screen bg-[#f8f5f2] p-6">
      <div className="max-w-7xl mx-auto">
        <h1 className="text-3xl font-bold text-[#8B4513] mb-8">Potato Credit Tracker Dashboard</h1>
        
        {/* Stats Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
          {stats.map((stat) => (
            <Card 
              key={stat.id}
              className={`border-l-4 border-[#8B4513] hover:shadow-lg transition-all duration-300 transform ${
                hoveredCard === stat.id ? "scale-105" : ""
              }`}
              onMouseEnter={() => setHoveredCard(stat.id)}
              onMouseLeave={() => setHoveredCard(null)}
            >
              <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
                <CardTitle className="text-sm font-medium text-[#5D4037]">
                  {stat.title}
                </CardTitle>
                <div className={`p-2 rounded-full ${stat.color}`}>
                  <stat.icon className={`h-4 w-4 ${stat.iconColor}`} />
                </div>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-[#8B4513]">{stat.value}</div>
                <div className="flex items-center text-xs text-gray-600">
                  {stat.trend === "up" ? (
                    <ArrowUp className="h-3 w-3 text-green-600 mr-1" />
                  ) : (
                    <ArrowDown className="h-3 w-3 text-red-600 mr-1" />
                  )}
                  <span className={stat.trend === "up" ? "text-green-600" : "text-red-600"}>
                    {stat.change}
                  </span>
                  <span className="ml-1">{stat.description}</span>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>

        {/* Content Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
          {/* Vehicle Performance */}
          <Card className="lg:col-span-2 border-l-4 border-[#8B4513] hover:shadow-lg transition-all duration-300">
            <CardHeader>
              <CardTitle className="text-[#8B4513]">Vehicle Performance</CardTitle>
              <CardDescription>Outstanding debt by vehicle</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="space-y-4">
                {/* Vehicle 1 */}
                <div>
                  <div className="flex justify-between mb-1">
                    <span className="text-sm font-medium text-[#5D4037]">Store North</span>
                    <span className="text-sm text-[#8B4513]">₦45,200</span>
                  </div>
                  <div className="w-full bg-gray-200 rounded-full h-2.5">
                    <div className="bg-[#8B4513] h-2.5 rounded-full" style={{ width: "70%" }}></div>
                  </div>
                </div>
                
                {/* Vehicle 2 */}
                <div>
                  <div className="flex justify-between mb-1">
                    <span className="text-sm font-medium text-[#5D4037]">Lorry B23</span>
                    <span className="text-sm text-[#8B4513]">₦32,800</span>
                  </div>
                  <div className="w-full bg-gray-200 rounded-full h-2.5">
                    <div className="bg-[#8B4513] h-2.5 rounded-full" style={{ width: "50%" }}></div>
                  </div>
                </div>
                
                {/* Vehicle 3 */}
                <div>
                  <div className="flex justify-between mb-1">
                    <span className="text-sm font-medium text-[#5D4037]">Store West</span>
                    <span className="text-sm text-[#8B4513]">₦28,600</span>
                  </div>
                  <div className="w-full bg-gray-200 rounded-full h-2.5">
                    <div className="bg-[#8B4513] h-2.5 rounded-full" style={{ width: "40%" }}></div>
                  </div>
                </div>
                
                {/* Vehicle 4 */}
                <div>
                  <div className="flex justify-between mb-1">
                    <span className="text-sm font-medium text-[#5D4037]">Lorry A45</span>
                    <span className="text-sm text-[#8B4513]">₦21,800</span>
                  </div>
                  <div className="w-full bg-gray-200 rounded-full h-2.5">
                    <div className="bg-[#8B4513] h-2.5 rounded-full" style={{ width: "30%" }}></div>
                  </div>
                </div>
              </div>
              
              <div className="mt-6">
                <a href="stores.php" className="text-[#8B4513] hover:underline font-medium flex items-center">
                  View all vehicles
                  <TrendingUp className="ml-1 h-4 w-4" />
                </a>
              </div>
            </CardContent>
          </Card>
          
          {/* Recent Activity */}
          <Card className="border-l-4 border-[#8B4513] hover:shadow-lg transition-all duration-300">
            <CardHeader>
              <CardTitle className="text-[#8B4513]">Recent Activity</CardTitle>
              <CardDescription>Latest system activities</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="space-y-4">
                {recentActivities.map((activity) => (
                  <div 
                    key={activity.id} 
                    className="p-3 bg-white border border-gray-100 rounded-lg hover:bg-[#f5efe6] transition-colors"
                  >
                    <div className="flex justify-between">
                      <h4 className="font-medium text-[#5D4037]">{activity.title}</h4>
                      {activity.amount && <span className="font-medium text-[#8B4513]">{activity.amount}</span>}
                    </div>
                    <div className="flex justify-between text-xs text-gray-500 mt-1">
                      <span>{activity.vehicle}</span>
                      <span>{activity.time}</span>
                    </div>
                  </div>
                ))}

                <div className="mt-2">
                  <a href="index.php" className="text-[#8B4513] hover:underline font-medium flex items-center">
                    View all activity
                    <TrendingUp className="ml-1 h-4 w-4" />
                  </a>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Quick Links */}
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4 mt-8">
          {[
            { title: "Customers", href: "customers.php", color: "bg-amber-100" },
            { title: "Debts", href: "debts.php", color: "bg-sky-100" },
            { title: "Sales", href: "sales.php", color: "bg-green-100" },
            { title: "Payments", href: "payments.php", color: "bg-purple-100" },
            { title: "Vehicles", href: "stores.php", color: "bg-pink-100" },
            { title: "Expenses", href: "expenses.php", color: "bg-orange-100" }
          ].map((link, index) => (
            <a 
              key={index} 
              href={link.href}
              className={`${link.color} text-[#5D4037] p-4 rounded-lg text-center font-medium hover:shadow-md transition-all duration-300 transform hover:scale-105`}
            >
              {link.title}
            </a>
          ))}
        </div>
      </div>
    </div>
  );
};

export default Index;
